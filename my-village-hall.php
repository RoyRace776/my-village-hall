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
use MYVH\Settings\GeneralSettings;
use MYVH\Calendar\CalendarShortcode;
use MYVH\Portal\ClientAdminService;
use MYVH\Bootstrap\Network\NetworkDashboard;
use MYVH\Bootstrap\Installer;
use MYVH\Login\PasswordResetLoader;
use MYVH\Core\Support\AssetLoader;
use MYVH\Settings\SettingsRegistry;
use MYVH\Settings\SettingsPage;

use Exception;


// Prevent direct file access — WordPress must load this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Plugin constants ──────────────────────────────────────────────────────────

define( 'MYVH_VERSION',          '0.2.0' );
define( 'MYVH_PLUGIN_DIR',       plugin_dir_path( __FILE__ ) );
define( 'MYVH_PLUGIN_URL',       plugin_dir_url( __FILE__ ) );
define( 'MYVH_PLUGIN_BASENAME',  plugin_basename( __FILE__ ) );

// ── Load required files ───────────────────────────────────────────────────────
// Wrapped in output buffering so that any accidental whitespace in included
// files doesn't trigger WordPress's "unexpected output" activation error.

ob_start();
require_once MYVH_PLUGIN_DIR . 'vendor/autoload.php';

// Dependency-injection container (returns a configured Container instance)
global $myvh_container;
$myvh_container = require MYVH_PLUGIN_DIR . 'src/Core/Support/myvh-container.php';


//require_once MYVH_PLUGIN_DIR . 'admin/views/settings-page.php';

// Bootstrap event listeners and any remaining wiring
require_once MYVH_PLUGIN_DIR . 'src/Bootstrap/myvh-bootstrap.php';


ob_end_clean();

// ── Main plugin class ─────────────────────────────────────────────────────────

/**
 * My_Village_Hall
 *
 * Singleton that owns WordPress hook registration, admin menu setup,
 * asset enqueueing, and page rendering.  Database installation lives
 * in Installer (called during activation).
 *
 * @since 0.1.0
 */
class MyVillageHall {

    /** @var MyVillageHall|null */
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
        add_action( 'admin_menu',     [ $this, 'ensure_invoices_menu_item' ], 99 );

        AssetLoader::init();

        // 2. Settings
        SettingsRegistry::auto_register( MYVH_PLUGIN_DIR . 'src/Settings' );
        ( new SettingsPage() )->init();

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
        $this->on_admin_post( 'myvh_save_booking',   BookingController::class, 'save' );
        $this->on_admin_post( 'myvh_cancel_booking', BookingController::class, 'cancel' );

        // Recurring patterns
        $this->on_admin_post( 'myvh_save_recurring_pattern',       RecurringPatternController::class, 'save' );
        $this->on_admin_post( 'myvh_delete_recurring_pattern',     RecurringPatternController::class, 'delete' );
        $this->on_admin_post( 'myvh_deactivate_recurring_pattern', RecurringPatternController::class, 'deactivate' );
        $this->on_admin_post( 'myvh_delete_future_bookings',       RecurringPatternController::class, 'delete_future_bookings' );
        $this->on_admin_post( 'myvh_process_patterns',             RecurringPatternController::class, 'process_patterns' );

        // Venues & Rooms
        $this->on_admin_post( 'myvh_save_venue',   VenueController::class, 'save' );
        $this->on_admin_post( 'myvh_delete_venue', VenueController::class, 'delete' );
        $this->on_admin_post( 'myvh_save_room',    RoomController::class,  'save' );
        $this->on_admin_post( 'myvh_delete_room',  RoomController::class,  'delete' );

        // Customers
        $this->on_admin_post( 'myvh_save_customer',          CustomerController::class,       'save' );
        $this->on_admin_post( 'myvh_delete_customer',        CustomerController::class,       'delete' );

        // Organisations
        $this->on_admin_post( 'myvh_save_organisation',   OrganisationController::class,      'save' );
        $this->on_admin_post( 'myvh_delete_organisation', OrganisationController::class,      'delete' );
        $this->on_admin_post( 'myvh_add_org_member',      OrganisationController::class,      'add_member' );
        $this->on_admin_post( 'myvh_remove_org_member',   OrganisationController::class,      'remove_member' );
        $this->on_admin_post( 'myvh_save_org_type',       OrganisationTypeController::class, 'save' );
        $this->on_admin_post( 'myvh_delete_org_type',     OrganisationTypeController::class, 'delete' );

        // Pricing
        $this->on_admin_post( 'myvh_save_rate',   RoomRateController::class, 'save' );
        $this->on_admin_post( 'myvh_delete_rate', RoomRateController::class, 'delete' );
        $this->on_admin_post( 'myvh_save_addon',  AddonController::class,     'save' );
        $this->on_admin_post( 'myvh_delete_addon', AddonController::class,    'delete' );

        // Invoices & Payments
        $this->on_admin_post( 'myvh_save_invoice',           InvoiceController::class, 'save' );
        $this->on_admin_post( 'myvh_delete_invoice',         InvoiceController::class, 'delete' );
        $this->on_admin_post( 'myvh_update_invoice_status',  InvoiceController::class, 'update_status' );
        $this->on_admin_post( 'myvh_record_payment',         InvoiceController::class, 'record_payment' );
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
     * Delegates actual table creation to Installer.
     */


    public function activate(): void {
        Installer::run();
        update_option( 'myvh_version', MYVH_VERSION );
        flush_rewrite_rules();
    }

    /** Runs on plugin deactivation. */
    public function deactivate($network_wide): void {
        $general_settings = new GeneralSettings();
        $delete_on_deactivate = (bool) $general_settings->get('delete_on_deactivate');

        if ($delete_on_deactivate) {
            Installer::tidy_up();
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

        add_submenu_page(
            'my-village-hall',
            __( 'Client Administrators', 'my-village-hall' ),
            __( 'Client Admins', 'my-village-hall' ),
            'manage_options',
            'myvh-client-admins-network',
            [ $this, 'render_client_admins_network_page' ]
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

    public function ensure_invoices_menu_item(): void {
        global $submenu;

        $parent_slug = 'my-village-hall';
        $target_slug = 'myvh-invoices';

        if (empty($submenu[$parent_slug]) || !is_array($submenu[$parent_slug])) {
            return;
        }

        foreach ($submenu[$parent_slug] as $item) {
            if (!empty($item[2]) && $item[2] === $target_slug) {
                return;
            }
        }

        add_submenu_page(
            $parent_slug,
            __( 'Invoices', 'my-village-hall' ),
            __( 'Invoices', 'my-village-hall' ),
            'manage_options',
            $target_slug,
            [ $this, 'render_invoices_page' ]
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
    public function render_client_admins_network_page(): void {

        if ( is_multisite() && current_user_can( 'manage_network_options' ) ) {
            $target = add_query_arg(
                [
                    'page' => 'myvh-network-client-admins',
                    'blog_id' => get_current_blog_id(),
                ],
                network_admin_url( 'admin.php' )
            );

            wp_safe_redirect( $target );
            exit;
        }

        if ( ! class_exists( 'ClientAdminService' ) ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Client Administrators', 'my-village-hall' ) . '</h1>';
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Client admin service is not available.', 'my-village-hall' ) . '</p></div>';
            echo '</div>';
            return;
        }

        if ( is_multisite() ) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Client Administrators', 'my-village-hall' ) . '</h1>';
            echo '<div class="notice notice-warning"><p>'
                . esc_html__( 'Only network administrators can manage cross-site client admin assignments. Ask a network admin to use Network Admin → Village Halls → Client Admins.', 'my-village-hall' )
                . '</p></div>';
            echo '</div>';
            return;
        }

        $service = new ClientAdminService();
        $blog_id = get_current_blog_id();

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_POST['myvh_client_admin_action'] ) ) {
            check_admin_referer( 'myvh_site_client_admins' );

            $action = sanitize_key( $_POST['myvh_client_admin_action'] );
            $redirect_args = [ 'page' => 'myvh-client-admins-network' ];

            if ( $action === 'add' ) {
                $identifier = sanitize_text_field( $_POST['user_identifier'] ?? '' );

                if ( $identifier === '' ) {
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
            } elseif ( $action === 'remove' ) {
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
            'added' => [ 'success', __( 'Client administrator added.', 'my-village-hall' ) ],
            'removed' => [ 'success', __( 'Client administrator removed.', 'my-village-hall' ) ],
            'missing_user' => [ 'error', __( 'Email address or username is required.', 'my-village-hall' ) ],
            'user_not_found' => [ 'error', __( 'No WordPress user was found with that email or username.', 'my-village-hall' ) ],
            'invalid_user' => [ 'error', __( 'Please select a valid user.', 'my-village-hall' ) ],
        ];

        $notice_key = sanitize_key( $_GET['myvh_notice'] ?? '' );
        $assigned_users = $service->get_assigned_users_for_blog( $blog_id );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Client Administrators', 'my-village-hall' ) . '</h1>';
        echo '<p>' . esc_html__( 'Assign users who can administer this client site in the portal.', 'my-village-hall' ) . '</p>';

        if ( isset( $notices[ $notice_key ] ) ) {
            $notice_type = $notices[ $notice_key ][0];
            $notice_text = $notices[ $notice_key ][1];
            echo '<div class="notice notice-' . esc_attr( $notice_type ) . ' is-dismissible"><p>' . esc_html( $notice_text ) . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url( add_query_arg( [ 'page' => 'myvh-client-admins-network' ], admin_url( 'admin.php' ) ) ) . '" style="max-width:620px; margin-bottom:24px;">';
        wp_nonce_field( 'myvh_site_client_admins' );
        echo '<input type="hidden" name="myvh_client_admin_action" value="add">';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr>';
        echo '<th scope="row"><label for="myvh-user-identifier">' . esc_html__( 'Email or username', 'my-village-hall' ) . '</label></th>';
        echo '<td><input id="myvh-user-identifier" type="text" name="user_identifier" class="regular-text" required></td>';
        echo '</tr>';
        echo '</tbody></table>';
        submit_button( __( 'Add Client Admin', 'my-village-hall' ) );
        echo '</form>';

        echo '<h2>' . esc_html__( 'Assigned Client Admins', 'my-village-hall' ) . '</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>' . esc_html__( 'Name', 'my-village-hall' ) . '</th><th>' . esc_html__( 'Email', 'my-village-hall' ) . '</th><th>' . esc_html__( 'Username', 'my-village-hall' ) . '</th><th style="width:120px;">' . esc_html__( 'Action', 'my-village-hall' ) . '</th></tr></thead><tbody>';

        if ( empty( $assigned_users ) ) {
            echo '<tr><td colspan="4">' . esc_html__( 'No explicit client admin assignments for this site.', 'my-village-hall' ) . '</td></tr>';
        } else {
            foreach ( $assigned_users as $assigned_user ) {
                echo '<tr>';
                echo '<td>' . esc_html( $assigned_user['display_name'] ?: $assigned_user['user_login'] ) . '</td>';
                echo '<td>' . esc_html( $assigned_user['user_email'] ) . '</td>';
                echo '<td>' . esc_html( $assigned_user['user_login'] ) . '</td>';
                echo '<td>';
                echo '<form method="post" action="' . esc_url( add_query_arg( [ 'page' => 'myvh-client-admins-network' ], admin_url( 'admin.php' ) ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Remove this client admin assignment?', 'my-village-hall' ) ) . '\');">';
                wp_nonce_field( 'myvh_site_client_admins' );
                echo '<input type="hidden" name="myvh_client_admin_action" value="remove">';
                echo '<input type="hidden" name="user_id" value="' . esc_attr( (int) $assigned_user['ID'] ) . '">';
                submit_button( __( 'Remove', 'my-village-hall' ), 'small', '', false );
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
        return;

    }
    public function render_customer_add_page():    void { $this->render_page( 'customer-add' ); }
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
 * Handles both single-site and multisite deactivation.
 */
function myvh_deactivate( bool $network_wide ): void {
    if ( is_multisite() && $network_wide ) {
        foreach ( get_sites( [ 'number' => 0 ] ) as $site ) {
            switch_to_blog( $site->blog_id );
            MyVillageHall::get_instance()->deactivate($network_wide);
            restore_current_blog();
        }
    } else {
        MyVillageHall::get_instance()->deactivate($network_wide);
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
    MyVillageHall::get_instance()->activate();
    restore_current_blog();
} );

// ── Plugin initialisation ─────────────────────────────────────────────────────

/**
 * Starts the plugin after all plugins have loaded.
 * Registers the calendar shortcode, AJAX handlers, and (on multisite)
 * the network dashboard.
 */
function myvh_init(): MyVillageHall {

    $plugin = \MyVillageHall::get_instance();

    // Frontend calendar shortcode + REST endpoint
    ( new CalendarShortcode() )->init();

    // Password reset shortcode and handler
    if (!class_exists('MYVH\Login\PasswordResetLoader')) {
        // Defensive: ensure class is loaded
        throw new Exception('PasswordResetLoader class not found.');
    }
    ( new PasswordResetLoader() )->init();

    // Network dashboard (multisite network-admin only)
    if ( is_multisite() && is_network_admin() ) {
        ( new NetworkDashboard() )->init();
    }

    return $plugin;
}

add_action( 'plugins_loaded', 'myvh_init' );
