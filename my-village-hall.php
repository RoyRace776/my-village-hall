<?php
/**
 * Plugin Name: My Village Hall
 * Plugin URI: https://example.com/my-village-hall
 * Description: A comprehensive venue and room booking management system with multi-client support, recurring bookings, and customer portal
 * Version: 0.1.0
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

// Prevent direct file access — WordPress must load this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Plugin constants ──────────────────────────────────────────────────────────

define( 'MYVH_VERSION',          '0.1.0' );
define( 'MYVH_PLUGIN_DIR',       plugin_dir_path( __FILE__ ) );
define( 'MYVH_PLUGIN_URL',       plugin_dir_url( __FILE__ ) );
define( 'MYVH_PLUGIN_BASENAME',  plugin_basename( __FILE__ ) );

// ── Load required files ───────────────────────────────────────────────────────
// Wrapped in output buffering so that any accidental whitespace in included
// files doesn't trigger WordPress's "unexpected output" activation error.

ob_start();

// Core infrastructure
require_once MYVH_PLUGIN_DIR . 'core/container/class-myvh-container.php';
require_once MYVH_PLUGIN_DIR . 'bootstrap/class-myvh-installer.php';
require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-asset-loader.php';

// Admin utilities
require_once MYVH_PLUGIN_DIR . 'admin/class-myvh-admin-notices.php';

// Data layer — repositories (class definitions only; no instantiation here)
require_once MYVH_PLUGIN_DIR . 'bootstrap/myvh-repositories.php';

// Service layer
require_once MYVH_PLUGIN_DIR . 'bootstrap/myvh-services.php';

// HTTP layer — controllers
require_once MYVH_PLUGIN_DIR . 'bootstrap/myvh-controllers.php';

// Dependency-injection container (returns a configured MYVH_Container instance)
global $myvh_container;
$myvh_container = require MYVH_PLUGIN_DIR . 'bootstrap/myvh-container.php';


// Settings module
require_once MYVH_PLUGIN_DIR . 'modules/settings/settings-helper.php';
require_once MYVH_PLUGIN_DIR . 'modules/settings/class-myvh-settings-base.php';
require_once MYVH_PLUGIN_DIR . 'modules/settings/class-myvh-general-settings.php';
require_once MYVH_PLUGIN_DIR . 'modules/settings/class-myvh-booking-settings.php';
require_once MYVH_PLUGIN_DIR . 'modules/settings/settings-registry.php';
require_once MYVH_PLUGIN_DIR . 'admin/views/settings-page.php';

// Bootstrap event listeners and any remaining wiring
require_once MYVH_PLUGIN_DIR . 'bootstrap/myvh-bootstrap.php';

// Feature modules
require_once MYVH_PLUGIN_DIR . 'modules/calendar/class-myvh-calendar-shortcode.php';

// Multisite network dashboard
require_once MYVH_PLUGIN_DIR . 'bootstrap/network/class-myvh-network-dashboard.php';
require_once MYVH_PLUGIN_DIR . 'bootstrap/network/class-myvh-network-sites-table.php';
require_once MYVH_PLUGIN_DIR . 'bootstrap/network/class-myvh-network-stats.php';

ob_end_clean();

// ── Main plugin class ─────────────────────────────────────────────────────────

/**
 * My_Village_Hall
 *
 * Singleton that owns WordPress hook registration, admin menu setup,
 * asset enqueueing, and page rendering.  Database installation lives
 * in MYVH_Installer (called during activation).
 *
 * @since 0.1.0
 */
class My_Village_Hall {

    /** @var My_Village_Hall|null */
    private static $instance = null;
    private static $separator_count = 0;

    // ── Singleton ─────────────────────────────────────────────────────────────

    /** Returns the single shared instance, creating it if necessary. */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Private: use get_instance() instead. */
    private function __construct() {
        $this->register_hooks();
    }

    // ── Hook registration ─────────────────────────────────────────────────────

    /**
     * Register every WordPress action/filter the plugin needs.
     *
     * Grouped into logical sections so the flow is easy to follow:
     *   1. Core WordPress integration (translations, menu, assets)
     *   2. Settings UI
     *   3. Admin-post actions (form submissions), grouped by domain
     */
    private function register_hooks(): void {

        // 1. Core WordPress integration
        add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
        add_action( 'admin_menu',     [ $this, 'register_admin_menu' ] );

        MYVH_Asset_Loader::init();

        // 2. Settings
        MYVH_Settings_Registry::auto_register( MYVH_PLUGIN_DIR . 'modules/settings' );
        ( new MYVH_Settings_Page() )->init();

        // 3. Form-submission handlers (admin-post actions)
        $this->register_admin_post_actions();
    }

    /**
     * Register admin_post_{action} hooks for every form in the plugin.
     *
     * Each handler simply resolves the right controller from the container
     * and calls the appropriate method.  Grouped by domain so it's easy to
     * see which actions belong together.
     */
    private function register_admin_post_actions(): void {

        // Bookings
        $this->on_admin_post( 'myvh_save_booking',   MYVH_Booking_Controller::class, 'save' );
        $this->on_admin_post( 'myvh_cancel_booking', MYVH_Booking_Controller::class, 'cancel' );

        // Recurring patterns
        $this->on_admin_post( 'myvh_save_recurring_pattern',       MYVH_Recurring_Pattern_Controller::class, 'save' );
        $this->on_admin_post( 'myvh_delete_recurring_pattern',     MYVH_Recurring_Pattern_Controller::class, 'delete' );
        $this->on_admin_post( 'myvh_deactivate_recurring_pattern', MYVH_Recurring_Pattern_Controller::class, 'deactivate' );
        $this->on_admin_post( 'myvh_delete_future_bookings',       MYVH_Recurring_Pattern_Controller::class, 'delete_future_bookings' );
        $this->on_admin_post( 'myvh_process_patterns',             MYVH_Recurring_Pattern_Controller::class, 'process_patterns' );

        // Venues & Rooms
        $this->on_admin_post( 'myvh_save_venue',   MYVH_Venue_Controller::class, 'save' );
        $this->on_admin_post( 'myvh_delete_venue', MYVH_Venue_Controller::class, 'delete' );
        $this->on_admin_post( 'myvh_save_room',    MYVH_Room_Controller::class,  'save' );
        $this->on_admin_post( 'myvh_delete_room',  MYVH_Room_Controller::class,  'delete' );

        // Customers
        $this->on_admin_post( 'myvh_save_customer',          MYVH_Customer_Controller::class,       'save' );
        $this->on_admin_post( 'myvh_delete_customer',        MYVH_Customer_Controller::class,       'delete' );

        // Organisations
        $this->on_admin_post( 'myvh_save_organisation',   MYVH_Organisation_Controller::class,      'save' );
        $this->on_admin_post( 'myvh_delete_organisation', MYVH_Organisation_Controller::class,      'delete' );
        $this->on_admin_post( 'myvh_add_org_member',      MYVH_Organisation_Controller::class,      'add_member' );
        $this->on_admin_post( 'myvh_remove_org_member',   MYVH_Organisation_Controller::class,      'remove_member' );
        $this->on_admin_post( 'myvh_save_org_type',       MYVH_Organisation_Type_Controller::class, 'save' );
        $this->on_admin_post( 'myvh_delete_org_type',     MYVH_Organisation_Type_Controller::class, 'delete' );

        // Pricing
        $this->on_admin_post( 'myvh_save_rate',   MYVH_Room_Rate_Controller::class, 'save' );
        $this->on_admin_post( 'myvh_delete_rate', MYVH_Room_Rate_Controller::class, 'delete' );
        $this->on_admin_post( 'myvh_save_addon',  MYVH_Addon_Controller::class,     'save' );
        $this->on_admin_post( 'myvh_delete_addon', MYVH_Addon_Controller::class,    'delete' );

        // Invoices & Payments
        $this->on_admin_post( 'myvh_save_invoice',           MYVH_Invoice_Controller::class, 'save' );
        $this->on_admin_post( 'myvh_delete_invoice',         MYVH_Invoice_Controller::class, 'delete' );
        $this->on_admin_post( 'myvh_update_invoice_status',  MYVH_Invoice_Controller::class, 'update_status' );
        $this->on_admin_post( 'myvh_record_payment',         MYVH_Invoice_Controller::class, 'record_payment' );
    }

    /**
     * Convenience wrapper: registers an admin_post_{$action} hook that
     * resolves $controller_class from the DI container and calls $method.
     */
    private function on_admin_post( string $action, string $controller_class, string $method ): void {
        add_action( "admin_post_{$action}", function () use ( $controller_class, $method ) {
            global $myvh_container;
            $myvh_container->get( $controller_class )->{$method}();
        } );
    }

    // ── Activation / deactivation ─────────────────────────────────────────────

    /**
     * Runs on plugin activation.
     * Delegates actual table creation to MYVH_Installer.
     */


    public function activate(): void {
        MYVH_Installer::run();
        update_option( 'myvh_version', MYVH_VERSION );
        flush_rewrite_rules();
    }

    /** Runs on plugin deactivation. */
    public function deactivate($network_wide): void {
        $general_settings = new MYVH_General_Settings();
        $delete_on_deactivate = (bool) $general_settings->get('delete_on_deactivate');

        if ($delete_on_deactivate) {
            MYVH_Installer::tidy_up();
        }
        flush_rewrite_rules();
    }

    // ── Translations ──────────────────────────────────────────────────────────

    /** Loads the plugin's translation files. */
    public function plugins_loaded(): void {

        load_plugin_textdomain(
            'my-village-hall',
            false,
            dirname( MYVH_PLUGIN_BASENAME ) . '/languages'
        );

    }

    // ── Admin menu ────────────────────────────────────────────────────────────

    /**
     * Registers the plugin's admin menu.
     *
     * Structure:
     *   My Village Hall (top-level)
     *   ├── All Bookings
     *   ├── Calendar
     *   ├── ── (separator) ──
     *   ├── Customers
     *   ├── ── (separator) ──
     *   ├── Venues
     *   ├── Rooms
     *   ├── ── (separator) ──
     *   ├── Room Rates
     *   ├── Add-ons
     *   ├── Invoices
     *   ├── ── (separator) ──
     *   └── Recurring Patterns
     */
    public function register_admin_menu(): void {

        // Top-level entry (renders the Bookings list)
        add_menu_page(
            __( 'My Village Hall', 'my-village-hall' ),
            __( 'My Village Hall', 'my-village-hall' ),
            'manage_options',
            'my-village-hall',
            [ $this, 'render_bookings_page' ],
            'dashicons-calendar-alt',
            30
        );

        // ── Bookings ──────────────────────────────────────────────────────────
        // WordPress automatically adds a duplicate of the top-level item as the
        // first submenu entry; we give it a friendlier label here.
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

        // ── Customers ─────────────────────────────────────────────────────────
        add_submenu_page( 'my-village-hall',
            __( 'Customers',       'my-village-hall' ),
            __( 'Customers',       'my-village-hall' ),
            'manage_options', 'myvh-customers', [ $this, 'render_customers_page' ]
        );

        $this->add_menu_separator();

        // ── Organisations ──────────────────────────────────────────────────────
        add_submenu_page( 'my-village-hall',
            __( 'Organisations',      'my-village-hall' ),
            __( 'Organisations',      'my-village-hall' ),
            'manage_options', 'myvh-organisations', [ $this, 'render_organisations_page' ]
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

        $this->add_menu_separator();

        // ── Venues & Rooms ────────────────────────────────────────────────────
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

        // ── Pricing & Billing ─────────────────────────────────────────────────
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
            __( 'Invoices', 'my-village-hall' ),
            __( 'Invoices', 'my-village-hall' ),
            'manage_options', 'myvh-invoices', [ $this, 'render_invoices_page' ]
        );

        $this->add_menu_separator();

        // ── Recurring bookings ────────────────────────────────────────────────
        add_submenu_page( 'my-village-hall',
            __( 'Recurring Bookings',  'my-village-hall' ),
            __( 'Recurring Patterns',  'my-village-hall' ),
            'manage_options', 'myvh-recurring', [ $this, 'render_recurring_page' ]
        );
    }

    /**
     * Adds a visual divider in the admin submenu.
     * Uses a non-functional "#" slug so WordPress still registers the entry.
     */
    public static function add_menu_separator(): void {
        self::$separator_count++;
        $slug = 'myvh-separator-' . self::$separator_count;

        add_submenu_page(
            'my-village-hall',
            '',
            ' ',
            'manage_options',
            $slug,
            '__return_null'
        );

        add_action('admin_head', function() use ($slug) {

            echo '<style>
            li a[href="admin.php?page=' . esc_attr($slug) . '"] {
                pointer-events:none;
                cursor:default;
                height:10px;
                margin:6px 0;
                padding:0;
                border-top:1px solid rgba(255,255,255,0.2);
            }

            li a[href="admin.php?page=' . esc_attr($slug) . '"] span {
                display:none;
            }
            </style>';

        });
    }


    // ── Page renderers ────────────────────────────────────────────────────────
    // Each method checks capability then includes the relevant view file.
    // A private helper keeps things DRY.

    public function render_bookings_page():        void { $this->render_page( 'bookings',        true ); }
    public function render_calendar_page():        void { $this->render_page( 'calendar' ); }
    public function render_customers_page():       void { $this->render_page( 'customers' ); }
    public function render_organisations_page():   void { $this->render_page( 'organisations' ); }
    public function render_org_types_page():       void { $this->render_page( 'org-types' ); }
    public function render_org_members_page():     void { $this->render_page( 'org-members' ); }
    public function render_venues_page():          void { $this->render_page( 'venues' ); }
    public function render_rooms_page():           void { $this->render_page( 'rooms' ); }
    public function render_room_rates_page():      void { $this->render_page( 'room-rates' ); }
    public function render_addons_page():          void { $this->render_page( 'addons' ); }
    public function render_invoices_page():        void { $this->render_page( 'invoices' ); }
    public function render_recurring_page():       void { $this->render_page( 'recurring' ); }

    /**
     * Capability-checks, then includes the view file for $page.
     *
     * For the bookings page only, a query-string flag switches to the
     * form view (add / edit / view a single booking).
     *
     * @param string $page            Slug matching the view filename, e.g. 'venues'.
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

        if ( $has_detail_view
             && ( isset( $_GET['add'] ) || isset( $_GET['edit'] ) || isset( $_GET['view'] ) ) ) {
            include MYVH_PLUGIN_DIR . "/admin/views/{$page}-form-page.php";
        } else {
            include MYVH_PLUGIN_DIR . "/admin/views/{$page}-page.php";
        }
    }
}

// ── Activation / deactivation hooks ──────────────────────────────────────────

/**
 * Handles both single-site and multisite (network) activation.
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
            My_Village_Hall::get_instance()->activate();
            restore_current_blog();
        }
    } else {
        $grant_cap();
        My_Village_Hall::get_instance()->activate();
    }
}
register_activation_hook( __FILE__, 'myvh_activate' );

/**
 * Handles both single-site and multisite deactivation.
 */
function myvh_deactivate( bool $network_wide ): void {
    if ( is_multisite() && $network_wide ) {
        foreach ( get_sites( [ 'number' => 0 ] ) as $site ) {
            switch_to_blog( $site->blog_id );
            My_Village_Hall::get_instance()->deactivate($network_wide);
            restore_current_blog();
        }
    } else {
        My_Village_Hall::get_instance()->deactivate($network_wide);
    }
}
register_deactivation_hook( __FILE__, 'myvh_deactivate' );

/**
 * Runs activation on newly-created sites in a multisite network.
 */
add_action( 'wp_initialize_site', function ( $new_site ) {
    $active_sitewide_plugins = (array) get_site_option( 'active_sitewide_plugins', [] );
    if ( ! isset( $active_sitewide_plugins[ MYVH_PLUGIN_BASENAME ] ) ) {
        return;
    }

    switch_to_blog( $new_site->blog_id );
    $role = get_role( 'administrator' );
    if ( $role && ! $role->has_cap( 'manage_myvh' ) ) {
        $role->add_cap( 'manage_myvh' );
    }
    My_Village_Hall::get_instance()->activate();
    restore_current_blog();
} );

// ── Plugin initialisation ─────────────────────────────────────────────────────

/**
 * Starts the plugin after all plugins have loaded.
 * Registers the calendar shortcode, AJAX handlers, and (on multisite)
 * the network dashboard.
 */
function myvh_init(): My_Village_Hall {

    $plugin = My_Village_Hall::get_instance();

    // Frontend calendar shortcode + REST endpoint
    ( new MYVH_Calendar_Shortcode() )->init();

    // Network dashboard (multisite network-admin only)
    if ( is_multisite() && is_network_admin() ) {
        ( new MYVH_Network_Dashboard() )->init();
    }

    return $plugin;
}

add_action( 'plugins_loaded', 'myvh_init' );
