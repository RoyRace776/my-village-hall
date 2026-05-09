<?php
/**
 * Plugin Name: My Village Hall
 * Plugin URI: https://example.com/my-village-hall
 * Description: A comprehensive venue and room booking management system with multi-client support, recurring bookings, and customer portal
 * Version: 0.6.2
 * Author: Richard Barrett
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: my-village-hall
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package MyVillageHall
 */

use MYVH\Venues\VenueController;
use MYVH\Rooms\RoomController;
use MYVH\Bookings\BookingController;
use MYVH\Bookings\RecurringPatternController;
use MYVH\Customers\CustomerController;
use MYVH\Organisations\OrganisationController;
use MYVH\Organisations\OrganisationTypeController;
use MYVH\Pricing\RoomRateController;
use MYVH\Addons\AddonController;
use MYVH\Invoices\InvoiceController;
use MYVH\Payments\PaymentController;
use MYVH\Settings\GeneralSettings;
use MYVH\Calendar\CalendarShortcode;
use MYVH\Calendar\CalendarStatusColours;
use MYVH\Availability\AvailabilityService;
use MYVH\Portal\ClientAdminService;
use MYVH\Network\NetworkDashboard;
use MYVH\Network\SiteSeeder;
use MYVH\Bootstrap\Installer;
use MYVH\Login\PasswordResetLoader;
use MYVH\Audit\AuditTrail;
use MYVH\Core\Support\AssetLoader;
use MYVH\Container\Container;
use MYVH\Settings\SettingsRegistry;
use MYVH\Settings\SettingsPage;
use MYVH\Network\SiteProvisioningRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MYVH_VERSION',         '0.6.2' );
define( 'MYVH_PLUGIN_DIR',      plugin_dir_path( __FILE__ ) );
define( 'MYVH_PLUGIN_URL',      plugin_dir_url( __FILE__ ) );
define( 'MYVH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Output buffering prevents accidental whitespace in included files from
// triggering WordPress's "unexpected output" activation error.
ob_start();
require_once MYVH_PLUGIN_DIR . 'vendor/autoload.php';
require_once MYVH_PLUGIN_DIR . 'src/Settings/settings-helper.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use YahnisElsts\PluginUpdateChecker\v5p6\Vcs\GitHubApi;
use YahnisElsts\PluginUpdateChecker\v5p6\Vcs\PluginUpdateChecker;

/** @var PluginUpdateChecker $updateChecker */
$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/RoyRace776/my-village-hall',
    __FILE__,
    'my-village-hall'
);

$updateChecker->addQueryArgFilter( function ( $queryArgs ) {
    $queryArgs['key'] = defined( 'MYVH_UPDATE_KEY' ) ? MYVH_UPDATE_KEY : '';
    return $queryArgs;
} );

$updateChecker->setBranch( 'main' );

// Use release assets (e.g. my-village-hall.zip) rather than auto-generated
// source archives, which exclude vendor/ due to .gitignore.
/** @var GitHubApi $updateApi */
$updateApi = $updateChecker->getVcsApi();
if ( is_object( $updateApi ) && method_exists( $updateApi, 'enableReleaseAssets' ) ) {
    call_user_func( [ $updateApi, 'enableReleaseAssets' ], '/my-village-hall\.zip$/i' );
}

// Dependency-injection container
global $myvh_container;
/** @var Container $myvh_container */
$myvh_container = require MYVH_PLUGIN_DIR . 'src/Core/Support/myvh-container.php';

//Put in hooks for database upgrades on version change, and for multisite activation.
add_action('plugins_loaded', function () {

    if (is_admin()) {
        add_action('admin_init', [Installer::class, 'maybe_upgrade']);
    }

});

register_activation_hook(__FILE__, function ($network_wide) {

    if (is_multisite() && $network_wide) {

        $sites = get_sites();

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            Installer::maybe_upgrade();

            restore_current_blog();
        }

    } else {
        Installer::maybe_upgrade();
    }

});

add_action('wp_initialize_site', function ($site) {

    switch_to_blog($site->blog_id);

    Installer::maybe_upgrade();

    restore_current_blog();

});

// Bootstrap event listeners and remaining wiring
require_once MYVH_PLUGIN_DIR . 'src/Bootstrap/myvh-bootstrap.php';

ob_end_clean();


/**
 * MyVillageHall
 *
 * Singleton responsible for WordPress hook registration, admin menu setup,
 * asset enqueueing, and page rendering. Database installation is delegated
 * to Installer (called on activation and on version change).
 *
 * @since 0.1.0
 */
class MyVillageHall {

    /** @var MyVillageHall|null */
    private static $instance = null;

    /** @var int Tracks separator slugs to keep them unique. */
    private static $separator_count = 0;


    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    /** Returns the single shared instance, creating it if necessary. */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Private constructor ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â use get_instance(). */
    private function __construct() {
        $this->register_hooks();
    }

    /**
     * Register every WordPress action/filter the plugin needs.
     *
     * Grouped into logical sections:
     *   1. Core WordPress integration (translations, menu, assets)
     *   2. Settings UI
     *   3. Admin-post form-submission handlers, grouped by domain
     *   4. Miscellaneous event listeners
     */
    private function register_hooks(): void {

        // 1. Core WordPress integration
        add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
        add_action( 'init',           [ $this, 'on_init' ] );
        add_action( 'admin_menu',     [ $this, 'register_admin_menu' ] );

        AssetLoader::init();

        // 2. Settings
        SettingsRegistry::auto_register( MYVH_PLUGIN_DIR . 'src/Settings' );
        ( new SettingsPage() )->init();

        // 3. Form-submission handlers
        $this->register_admin_post_actions();

        // 4. Miscellaneous event listeners
        add_action( 'myvh_event_site.clone_failed', [ $this, 'handle_clone_failed' ] );
        add_action( 'myvh_site_cloned',             [ $this, 'handle_site_cloned' ], 10, 2 );
        add_action( 'wp_delete_site',               [ $this, 'handle_site_deleted' ], 10, 1 );
    }


    // -------------------------------------------------------------------------
    // WordPress hooks
    // -------------------------------------------------------------------------

    /**
     * Loads translations only. Any code that touches the database or Action
     * Scheduler must go in on_init() to ensure AS is fully initialised.
     */
    public function on_plugins_loaded(): void {
        load_plugin_textdomain(
            'my-village-hall',
            false,
            dirname( MYVH_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Runs on the `init` hook, after Action Scheduler's data store is ready.
     * Handles database upgrades triggered by a version change.
     */
    public function on_init(): void {
        if ( get_option( 'myvh_version' ) !== MYVH_VERSION ) {
            Installer::run();
            update_option( 'myvh_version', MYVH_VERSION );
        }
    }


    // -------------------------------------------------------------------------
    // Activation / deactivation
    // -------------------------------------------------------------------------

    /** Runs on plugin activation. */
    public function activate(): void {
        Installer::run();
        update_option( 'myvh_version', MYVH_VERSION );
        flush_rewrite_rules();
    }

    /** Runs on plugin deactivation. */
    public function deactivate( bool $network_wide ): void {
        $general_settings    = new GeneralSettings();
        $delete_on_deactivate = (bool) $general_settings->get( 'delete_on_deactivate' );

        if ( $delete_on_deactivate ) {
            Installer::tidy_up();
        }

        flush_rewrite_rules();
    }


    // -------------------------------------------------------------------------
    // Event listeners
    // -------------------------------------------------------------------------

    /** Marks a provisioning record as failed when a site clone fails. */
    public function handle_clone_failed( $data ): void {
        if ( empty( $data['provision_id'] ) ) {
            return;
        }

        ( new SiteProvisioningRepository() )->update_status(
            $data['provision_id'],
            'failed',
            [ 'error' => $data['reason'] ?? '' ]
        );
    }

    /** Seeds a newly-cloned site. */
    public function handle_site_cloned( $blog_id, $context ): void {
        ( new SiteSeeder() )->seed( $blog_id, $context );
    }

    /** Drops plugin tables and removes provisioning records for a deleted site. */
    public function handle_site_deleted( $site ): void {
        global $wpdb;
        Installer::drop_tables( $wpdb );

        $site_id = is_object( $site ) ? $site->blog_id : (int) $site;
        $repo    = new SiteProvisioningRepository();

        if ( $records = $repo->get_by_site_id( $site_id ) ) {
            foreach ( $records as $record ) {
                $repo->delete( $record->id );
            }
        }
    }


    // -------------------------------------------------------------------------
    // Admin-post handlers
    // -------------------------------------------------------------------------

    /**
     * Registers admin_post_{action} hooks for every form in the plugin,
     * grouped by domain.
     */
    private function register_admin_post_actions(): void {

        // Bookings
        $this->on_admin_post( 'myvh_save_booking',   BookingController::class, 'save' );
        $this->on_admin_post( 'myvh_cancel_booking', BookingController::class, 'cancel' );

        // Recurring patterns
        $this->on_admin_post( 'myvh_save_recurring_pattern',       RecurringPatternController::class, 'save' );
        $this->on_admin_post( 'myvh_delete_recurring_pattern',     RecurringPatternController::class, 'delete' );
        $this->on_admin_post( 'myvh_deactivate_recurring_pattern', RecurringPatternController::class, 'deactivate' );
        $this->on_admin_post( 'myvh_delete_future_bookings',       RecurringPatternController::class, 'delete_future_bookings' );
        $this->on_admin_post( 'myvh_process_patterns',             RecurringPatternController::class, 'process_patterns' );

        // Venues & rooms
        $this->on_admin_post( 'myvh_save_venue',   VenueController::class, 'save' );
        $this->on_admin_post( 'myvh_delete_venue', VenueController::class, 'delete' );
        $this->on_admin_post( 'myvh_save_room',    RoomController::class,  'save' );
        $this->on_admin_post( 'myvh_delete_room',  RoomController::class,  'delete' );

        // Customers
        $this->on_admin_post( 'myvh_save_customer',   CustomerController::class, 'save' );
        $this->on_admin_post( 'myvh_delete_customer', CustomerController::class, 'delete' );

        // Organisations
        $this->on_admin_post( 'myvh_save_organisation',   OrganisationController::class,     'save' );
        $this->on_admin_post( 'myvh_delete_organisation', OrganisationController::class,     'delete' );
        $this->on_admin_post( 'myvh_add_org_member',      OrganisationController::class,     'add_member' );
        $this->on_admin_post( 'myvh_remove_org_member',   OrganisationController::class,     'remove_member' );
        $this->on_admin_post( 'myvh_save_org_type',       OrganisationTypeController::class, 'save' );
        $this->on_admin_post( 'myvh_delete_org_type',     OrganisationTypeController::class, 'delete' );

        // Pricing
        $this->on_admin_post( 'myvh_save_rate',    RoomRateController::class, 'save' );
        $this->on_admin_post( 'myvh_delete_rate',  RoomRateController::class, 'delete' );
        $this->on_admin_post( 'myvh_save_addon',   AddonController::class,    'save' );
        $this->on_admin_post( 'myvh_delete_addon', AddonController::class,    'delete' );

        // Invoices & payments
        $this->on_admin_post( 'myvh_save_invoice',          InvoiceController::class, 'save' );
        $this->on_admin_post( 'myvh_generate_invoices',     InvoiceController::class, 'generate_from_bookings' );
        $this->on_admin_post( 'myvh_view_invoice_pdf',      InvoiceController::class, 'view_pdf' );
        $this->on_admin_post( 'myvh_email_invoice',         InvoiceController::class, 'email_invoice' );
        $this->on_admin_post( 'myvh_delete_invoice',        InvoiceController::class, 'delete' );
        $this->on_admin_post( 'myvh_update_invoice_status', InvoiceController::class, 'update_status' );
        $this->on_admin_post( 'myvh_record_payment',        PaymentController::class, 'create' );
        $this->on_admin_post( 'myvh_delete_payment',        PaymentController::class, 'delete' );

        // Auditing
        add_action( 'admin_post_myvh_reset_audit_log', [ $this, 'reset_audit_log' ] );
    }

    /**
     * Convenience wrapper: registers an admin_post_{$action} hook that resolves
     * $controller_class from the DI container and calls $method on it.
     */
    private function on_admin_post( string $action, string $controller_class, string $method ): void {
        add_action( "admin_post_{$action}", function () use ( $controller_class, $method ) {
            global $myvh_container;
            $myvh_container->get( $controller_class )->{$method}();
        } );
    }


    // -------------------------------------------------------------------------
    // Admin menu
    // -------------------------------------------------------------------------

    /**
     * Registers the plugin's admin submenu.
     *
     * Structure:
     *   My Village Hall  (top-level ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ Bookings list)
     *     All Bookings
     *     Calendar
     *     ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ separator ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬
     *     Customers
     *     ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ separator ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬
     *     Organisations / Org Types / Org Members / Client Admins
     *     ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ separator ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬
     *     Venues / Rooms
     *     ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ separator ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬
     *     Room Rates / Add-ons / Invoices / Payments / Generate Invoices
     *     ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ separator ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬
     *     Recurring Patterns
     *     Audit Log  (conditional)
     */
    public function register_admin_menu(): void {

        add_menu_page(
            __( 'My Village Hall', 'my-village-hall' ),
            __( 'My Village Hall', 'my-village-hall' ),
            'manage_options',
            'my-village-hall',
            [ $this, 'render_bookings_page' ],
            'dashicons-calendar-alt',
            30
        );

        // WordPress duplicates the top-level item as the first submenu entry;
        // give it a friendlier label.
        add_submenu_page( 'my-village-hall',
            __( 'All Bookings', 'my-village-hall' ),
            __( 'All Bookings', 'my-village-hall' ),
            'manage_options', 'my-village-hall', [ $this, 'render_bookings_page' ]
        );

        add_submenu_page( 'my-village-hall',
            __( 'Booking Calendar', 'my-village-hall' ),
            __( 'Calendar',         'my-village-hall' ),
            'manage_options', 'myvh-calendar', [ $this, 'render_calendar_page' ]
        );

        $this->add_menu_separator();

        add_submenu_page( 'my-village-hall',
            __( 'Customers', 'my-village-hall' ),
            __( 'Customers', 'my-village-hall' ),
            'manage_options', 'myvh-customers', [ $this, 'render_customers_page' ]
        );

        $this->add_menu_separator();

        add_submenu_page( 'my-village-hall',
            __( 'Organisations', 'my-village-hall' ),
            __( 'Organisations', 'my-village-hall' ),
            'manage_options', 'myvh-organisations', [ $this, 'render_organisations_page' ]
        );

        // Hidden from the menu (reached via redirect from Organisations).
        add_submenu_page( '',
            __( 'Add Organisation', 'my-village-hall' ),
            __( 'Add Organisation', 'my-village-hall' ),
            'manage_options',
            'myvh-organisation-add',
            [ $this, 'render_organisation_add_page' ]
        );

        add_submenu_page( 'my-village-hall',
            __( 'Organisation Types', 'my-village-hall' ),
            __( 'Org Types',          'my-village-hall' ),
            'manage_options', 'myvh-org-types', [ $this, 'render_org_types_page' ]
        );

        add_submenu_page( 'my-village-hall',
            __( 'Organisation Members', 'my-village-hall' ),
            __( 'Org Members',          'my-village-hall' ),
            'manage_options', 'myvh-org-members', [ $this, 'render_org_members_page' ]
        );

        add_submenu_page( 'my-village-hall',
            __( 'Client Administrators', 'my-village-hall' ),
            __( 'Client Admins',         'my-village-hall' ),
            'manage_options',
            'myvh-client-admins-network',
            [ $this, 'render_client_admins_network_page' ]
        );

        $this->add_menu_separator();

        add_submenu_page( 'my-village-hall',
            __( 'Venues', 'my-village-hall' ),
            __( 'Venues', 'my-village-hall' ),
            'manage_options', 'myvh-venues', [ $this, 'render_venues_page' ]
        );

        add_submenu_page( 'my-village-hall',
            __( 'Rooms', 'my-village-hall' ),
            __( 'Rooms', 'my-village-hall' ),
            'manage_options', 'myvh-rooms', [ $this, 'render_rooms_page' ]
        );

        $this->add_menu_separator();

        add_submenu_page( 'my-village-hall',
            __( 'Room Rates', 'my-village-hall' ),
            __( 'Room Rates', 'my-village-hall' ),
            'manage_options', 'myvh-room-rates', [ $this, 'render_room_rates_page' ]
        );

        add_submenu_page( 'my-village-hall',
            __( 'Add-ons', 'my-village-hall' ),
            __( 'Add-ons', 'my-village-hall' ),
            'manage_options', 'myvh-addons', [ $this, 'render_addons_page' ]
        );

        add_submenu_page( 'my-village-hall',
            __( 'View Invoices', 'my-village-hall' ),
            __( 'View Invoices', 'my-village-hall' ),
            'manage_options', 'myvh-invoices', [ $this, 'render_invoices_page' ]
        );

        add_submenu_page( 'my-village-hall',
            __( 'Payments', 'my-village-hall' ),
            __( 'Payments', 'my-village-hall' ),
            'manage_options', 'myvh-payments', [ $this, 'render_payments_page' ]
        );

        add_submenu_page( 'my-village-hall',
            __( 'Generate Invoices', 'my-village-hall' ),
            __( 'Generate Invoices', 'my-village-hall' ),
            'manage_options', 'myvh-invoice-generate', [ $this, 'render_invoice_generate_page' ]
        );

        $this->add_menu_separator();

        add_submenu_page( 'my-village-hall',
            __( 'Recurring Bookings',  'my-village-hall' ),
            __( 'Recurring Patterns',  'my-village-hall' ),
            'manage_options', 'myvh-recurring', [ $this, 'render_recurring_page' ]
        );

        if ( AuditTrail::is_enabled() ) {
            add_submenu_page( 'my-village-hall',
                __( 'Audit Log', 'my-village-hall' ),
                __( 'Audit Log', 'my-village-hall' ),
                'manage_options',
                'myvh-audit-log',
                [ $this, 'render_audit_log_page' ]
            );
        }
    }

    /**
     * Inserts a visual divider in the admin submenu.
     * A non-functional slug keeps WordPress happy while CSS hides the label.
     */
    public static function add_menu_separator(): void {
        self::$separator_count++;
        $slug = 'myvh-separator-' . self::$separator_count;

        add_submenu_page( 'my-village-hall', '', ' ', 'manage_options', $slug, '__return_null' );

        add_action( 'admin_head', function () use ( $slug ) {
            $safe = esc_attr( $slug );
            echo "<style>
li a[href=\"admin.php?page={$safe}\"] {
    pointer-events: none;
    cursor: default;
    height: 10px;
    margin: 6px 0;
    padding: 0;
    border-top: 1px solid rgba(255,255,255,0.2);
}
li a[href=\"admin.php?page={$safe}\"] span { display: none; }
</style>";
        } );
    }


    // -------------------------------------------------------------------------
    // Page renderers
    // -------------------------------------------------------------------------

    public function render_bookings_page(): void          { $this->render_page( 'bookings', true ); }
    public function render_customers_page(): void         { $this->render_page( 'customers' ); }
    public function render_org_types_page(): void         { $this->render_page( 'org-types' ); }
    public function render_org_members_page(): void       { $this->render_page( 'org-members' ); }
    public function render_payments_page(): void          { $this->render_page( 'payments' ); }
    public function render_customer_add_page(): void      { $this->render_page( 'customer-add' ); }
    public function render_organisation_add_page(): void  { $this->render_page( 'organisation-add' ); }
    public function render_venues_page(): void            { $this->render_page( 'venues' ); }
    public function render_rooms_page(): void             { $this->render_page( 'rooms' ); }
    public function render_room_rates_page(): void        { $this->render_page( 'room-rates' ); }
    public function render_addons_page(): void            { $this->render_page( 'addons' ); }
    public function render_invoices_page(): void          { $this->render_page( 'invoices', true ); }
    public function render_invoice_generate_page(): void  { $this->render_page( 'invoice-generate' ); }
    public function render_recurring_page(): void         { $this->render_page( 'recurring' ); }
    public function render_audit_log_page(): void         { $this->render_page( 'audit-log' ); }

    public function render_organisations_page(): void {
        if ( isset( $_GET['add'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=myvh-organisation-add' ) );
            exit;
        }
        $this->render_page( 'organisations' );
    }

    public function render_calendar_page(): void {
        // Resolve visible hours here so AssetLoader stays free of DB calls.
        $visible_hours = [ 'start' => 8, 'end' => 22 ];

        global $myvh_container;

        if ( isset( $myvh_container ) ) {
            try {
                $hours = $myvh_container->get( AvailabilityService::class )->get_calendar_visible_hours();
                if ( ! empty( $hours ) && is_array( $hours ) ) {
                    $visible_hours = [
                        'start' => (int) ( $hours['start'] ?? 8 ),
                        'end'   => (int) ( $hours['end']   ?? 22 ),
                    ];
                }
            } catch ( \Throwable $e ) {
                // Fall back to defaults silently.
            }
        }

        wp_localize_script( 'myvh-calendar-admin', 'myvhCal', [
            'ajax_url'             => admin_url( 'admin-ajax.php' ),
            'nonce'                => wp_create_nonce( 'myvh_calendar' ),
            'headerDateFormat'     => myvh_setting( 'calendar.calendar_date_format', 'd MMM' ),
            'startOfWeek'          => (int) get_option( 'start_of_week', 1 ),
            'maxBookingDaysAhead'  => (int) myvh_setting( 'booking.max_booking_days', 365 ),
            'visibleStartHour'     => $visible_hours['start'],
            'visibleEndHour'       => $visible_hours['end'],
            'schedulerOrientation' => myvh_setting( 'calendar.scheduler_orientation', 'horizontal' ),
            'statusColors'         => CalendarStatusColours::map(),
        ] );

        $this->render_page( 'calendar' );
    }

    public function render_client_admins_network_page(): void {

        // On multisite, network admins are redirected to the network admin screen.
        if ( is_multisite() && current_user_can( 'manage_network_options' ) ) {
            wp_safe_redirect( add_query_arg(
                [
                    'page'    => 'myvh-network-client-admins',
                    'blog_id' => get_current_blog_id(),
                ],
                network_admin_url( 'admin.php' )
            ) );
            exit;
        }

        if ( ! class_exists( 'ClientAdminService' ) ) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Client Administrators', 'my-village-hall' ) . '</h1>';
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Client admin service is not available.', 'my-village-hall' ) . '</p></div>';
            echo '</div>';
            return;
        }

        if ( is_multisite() ) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Client Administrators', 'my-village-hall' ) . '</h1>';
            echo '<div class="notice notice-warning"><p>'
                . esc_html__( 'Only network administrators can manage cross-site client admin assignments. Ask a network admin to use Network Admin ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ Client Admins.', 'my-village-hall' )
                . '</p></div>';
            echo '</div>';
            return;
        }

        $service  = new ClientAdminService();
        $blog_id  = get_current_blog_id();
        $page_url = add_query_arg( [ 'page' => 'myvh-client-admins-network' ], admin_url( 'admin.php' ) );

        // Handle form submissions.
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && ! empty( $_POST['myvh_client_admin_action'] ) ) {
            check_admin_referer( 'myvh_site_client_admins' );

            $action        = sanitize_key( $_POST['myvh_client_admin_action'] );
            $redirect_args = [ 'page' => 'myvh-client-admins-network' ];

            if ( 'add' === $action ) {
                $identifier = sanitize_text_field( $_POST['user_identifier'] ?? '' );

                if ( '' === $identifier ) {
                    $redirect_args['myvh_notice'] = 'missing_user';
                } else {
                    $user = $service->find_user( $identifier );
                    if ( $user instanceof WP_User ) {
                        $service->add_assignment( $blog_id, (int) $user->ID );
                        $redirect_args['myvh_notice'] = 'added';
                    } else {
                        $redirect_args['myvh_notice'] = 'user_not_found';
                    }
                }
            } elseif ( 'remove' === $action ) {
                $user_id = (int) ( $_POST['user_id'] ?? 0 );

                if ( $user_id > 0 ) {
                    $service->remove_assignment( $blog_id, $user_id );
                    $redirect_args['myvh_notice'] = 'removed';
                } else {
                    $redirect_args['myvh_notice'] = 'invalid_user';
                }
            }

            wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
            exit;
        }

        $notices = [
            'added'       => [ 'success', __( 'Client administrator added.',                                          'my-village-hall' ) ],
            'removed'     => [ 'success', __( 'Client administrator removed.',                                        'my-village-hall' ) ],
            'missing_user'  => [ 'error', __( 'Email address or username is required.',                               'my-village-hall' ) ],
            'user_not_found' => [ 'error', __( 'No WordPress user was found with that email or username.',            'my-village-hall' ) ],
            'invalid_user'  => [ 'error', __( 'Please select a valid user.',                                          'my-village-hall' ) ],
        ];

        $notice_key     = sanitize_key( $_GET['myvh_notice'] ?? '' );
        $assigned_users = $service->get_assigned_users_for_blog( $blog_id );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Client Administrators', 'my-village-hall' ) . '</h1>';
        echo '<p>' . esc_html__( 'Assign users who can administer this client site in the portal.', 'my-village-hall' ) . '</p>';

        if ( isset( $notices[ $notice_key ] ) ) {
            [ $type, $text ] = $notices[ $notice_key ];
            echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
        }

        // Add form
        echo '<form method="post" action="' . esc_url( $page_url ) . '" style="max-width:620px;margin-bottom:24px;">';
        wp_nonce_field( 'myvh_site_client_admins' );
        echo '<input type="hidden" name="myvh_client_admin_action" value="add">';
        echo '<table class="form-table" role="presentation"><tbody><tr>';
        echo '<th scope="row"><label for="myvh-user-identifier">' . esc_html__( 'Email or username', 'my-village-hall' ) . '</label></th>';
        echo '<td><input id="myvh-user-identifier" type="text" name="user_identifier" class="regular-text" required></td>';
        echo '</tr></tbody></table>';
        submit_button( __( 'Add Client Admin', 'my-village-hall' ) );
        echo '</form>';

        // Assigned users table
        echo '<h2>' . esc_html__( 'Assigned Client Admins', 'my-village-hall' ) . '</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Name',     'my-village-hall' ) . '</th>';
        echo '<th>' . esc_html__( 'Email',    'my-village-hall' ) . '</th>';
        echo '<th>' . esc_html__( 'Username', 'my-village-hall' ) . '</th>';
        echo '<th style="width:120px;">' . esc_html__( 'Action', 'my-village-hall' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $assigned_users ) ) {
            echo '<tr><td colspan="4">' . esc_html__( 'No explicit client admin assignments for this site.', 'my-village-hall' ) . '</td></tr>';
        } else {
            foreach ( $assigned_users as $u ) {
                $confirm_js = esc_js( __( 'Remove this client admin assignment?', 'my-village-hall' ) );
                echo '<tr>';
                echo '<td>' . esc_html( $u['display_name'] ?: $u['user_login'] ) . '</td>';
                echo '<td>' . esc_html( $u['user_email'] ) . '</td>';
                echo '<td>' . esc_html( $u['user_login'] ) . '</td>';
                echo '<td>';
                echo '<form method="post" action="' . esc_url( $page_url ) . '" onsubmit="return confirm(\'' . $confirm_js . '\');">';
                wp_nonce_field( 'myvh_site_client_admins' );
                echo '<input type="hidden" name="myvh_client_admin_action" value="remove">';
                echo '<input type="hidden" name="user_id" value="' . esc_attr( (int) $u['ID'] ) . '">';
                submit_button( __( 'Remove', 'my-village-hall' ), 'small', '', false );
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function reset_audit_log(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__( 'You do not have sufficient permissions to access this page.', 'my-village-hall' ),
                esc_html__( 'Access Denied', 'my-village-hall' ),
                [ 'response' => 403 ]
            );
        }

        check_admin_referer( 'myvh_reset_audit_log' );
        AuditTrail::reset();

        wp_safe_redirect( add_query_arg(
            [ 'page' => 'myvh-audit-log', 'reset' => 1 ],
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    /**
     * Capability-checks, then includes the appropriate view template.
     *
     * @param string $page            Slug matching the template filename, e.g. 'venues'.
     * @param bool   $has_detail_view Whether this page has an add/edit/view sub-view.
     */
    private function render_page( string $page, bool $has_detail_view = false ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__( 'You do not have sufficient permissions to access this page.', 'my-village-hall' ),
                esc_html__( 'Access Denied', 'my-village-hall' ),
                [ 'response' => 403 ]
            );
        }

        if ( $has_detail_view && ( isset( $_GET['add'] ) || isset( $_GET['edit'] ) || isset( $_GET['view'] ) ) ) {
            include MYVH_PLUGIN_DIR . "templates/Admin/{$page}-form-page.php";
        } else {
            include MYVH_PLUGIN_DIR . "templates/Admin/{$page}-page.php";
        }
    }
}


// =============================================================================
// Activation / deactivation hooks
// =============================================================================

/**
 * Handles both single-site and network-wide activation.
 */
function myvh_activate( bool $network_wide ): void {
    $grant_cap = function () {
        $role = get_role( 'administrator' );
        if ( $role && ! $role->has_cap( 'manage_myvh' ) ) {
            $role->add_cap( 'manage_myvh' );
        }
    };

    if ( is_multisite() && $network_wide ) {
        foreach ( get_sites( [ 'number' => 0 ] ) as $site ) {
            switch_to_blog( $site->blog_id );
            $grant_cap();
            MyVillageHall::get_instance()->activate();
            restore_current_blog();
        }
    } else {
        $grant_cap();
        MyVillageHall::get_instance()->activate();
    }
}
register_activation_hook( __FILE__, 'myvh_activate' );

/**
 * Handles both single-site and network-wide deactivation.
 */
function myvh_deactivate( bool $network_wide ): void {
    if ( is_multisite() && $network_wide ) {
        foreach ( get_sites( [ 'number' => 0 ] ) as $site ) {
            switch_to_blog( $site->blog_id );
            MyVillageHall::get_instance()->deactivate( $network_wide );
            restore_current_blog();
        }
    } else {
        MyVillageHall::get_instance()->deactivate( $network_wide );
    }
}
register_deactivation_hook( __FILE__, 'myvh_deactivate' );

/**
 * Activates the plugin on newly-created sites in a multisite network.
 */
add_action( 'wp_initialize_site', function ( $new_site ) {
    $active = (array) get_site_option( 'active_sitewide_plugins', [] );
    if ( ! isset( $active[ MYVH_PLUGIN_BASENAME ] ) ) {
        return;
    }

    switch_to_blog( $new_site->blog_id );
    $role = get_role( 'administrator' );
    if ( $role && ! $role->has_cap( 'manage_myvh' ) ) {
        $role->add_cap( 'manage_myvh' );
    }
    MyVillageHall::get_instance()->activate();
    restore_current_blog();
} );


// =============================================================================
// Filters
// =============================================================================

/**
 * Hides the front-end admin bar for portal users who lack plugin admin access.
 */
add_filter( 'show_admin_bar', function ( bool $show ): bool {
    if ( is_admin() || ! is_user_logged_in() ) {
        return $show;
    }
    return current_user_can( 'manage_myvh' ) ? $show : false;
} );


// =============================================================================
// Boot
// =============================================================================

/**
 * Initialises the plugin after all plugins have loaded.
 * Registers the calendar shortcode, password-reset shortcode, AJAX handlers,
 * and (on multisite) the network dashboard.
 */
add_action( 'plugins_loaded', function (): void {

    MyVillageHall::get_instance();

    ( new CalendarShortcode() )->init();
    ( new PasswordResetLoader() )->init();

    if ( is_multisite() && is_network_admin() ) {
        ( new NetworkDashboard() )->init();
    }
} );