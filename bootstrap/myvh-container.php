<?php
/**
 * DI Container setup
 *
 * Builds the MYVH_Container instance, registers all service providers,
 * and returns the configured container so the caller can assign it to
 * $myvh_container.
 *
 * Service providers are responsible for binding concrete implementations
 * to the container (repositories, services, controllers).  Each provider
 * lives alongside the module it serves under modules/{domain}/.
 *
 * Usage (from my-village-hall.php):
 *   $myvh_container = require MYVH_PLUGIN_DIR . 'bootstrap/myvh-container.php';
 */

use MyVH\Infrastructure\MYVH_Shortcode_Registry;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
global $myvh_container;

$myvh_container = new MYVH_Container();

// ── Require service-provider class files ──────────────────────────────────────
// Each provider is a single class that registers its module's bindings.

require_once MYVH_PLUGIN_DIR . 'modules/venues/class-venue-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-room-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/customers/class-customer-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-organisation-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-pricing-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/addons/class-addon-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/availability/class-availability-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-invoice-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/payments/class-payment-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/calendar/class-calendar-service-provider.php';

// Get the files needed for shortcodes
require_once MYVH_PLUGIN_DIR . 'core/shortcode/shortcode-interface.php';
require_once MYVH_PLUGIN_DIR . 'core/shortcode/class-myvh-shortcode-registry.php';
require_once MYVH_PLUGIN_DIR . 'modules/login/class-myvh-login-shortcode.php';
require_once MYVH_PLUGIN_DIR . 'modules/portal/class-myvh-portal-shortcode.php';
require_once MYVH_PLUGIN_DIR . 'modules/portal/class-myvh-portal-controller.php';

require_once MYVH_PLUGIN_DIR . 'modules/login/class-myvh-login-handler.php';


// Register wpdb so it can be injected like any other dependency
$myvh_container->singleton( \wpdb::class, function () {
    global $wpdb;
    return $wpdb;
} );

// ── Register providers with the container ─────────────────────────────────────
// Order follows the dependency graph (no provider should depend on a later one).

$providers = [
    MYVH_Venue_Service_Provider::class,
    MYVH_Room_Service_Provider::class,
    MYVH_Customer_Service_Provider::class,
    MYVH_Organisation_Service_Provider::class,
    MYVH_Pricing_Service_Provider::class,
    MYVH_Addon_Service_Provider::class,
    MYVH_Booking_Service_Provider::class,
    MYVH_Availability_Service_Provider::class,
    MYVH_Invoice_Service_Provider::class,
    MYVH_Payment_Service_Provider::class,
    MYVH_Calendar_Service_Provider::class,
];

foreach ( $providers as $provider ) {
    ( new $provider() )->register( $myvh_container );
}

$calendar_ajax = $myvh_container->get(MYVH_Calendar_Ajax_Controller::class);
$calendar_ajax->register_routes();

// Register the shortcodes
$registry = new MYVH_Shortcode_Registry();
add_action('init', [$registry, 'register']);

use MYVH\Shortcodes\MYVH_Login_Shortcode;
//use MYVH\Shortcodes\MYVH_Dashboard_Shortcode;

$registry->add($myvh_container->get(MYVH_Login_Shortcode::class));
$login_handler = new MYVH_Login_Handler();
$login_handler->init();

//$registry->add($myvh_container->get(MYVH_Dashboard_Shortcode::class));

$portal_shortcode = new MYVH_Portal_Shortcode();
$portal_shortcode->register();

$portal_controller = $myvh_container->get(MYVH_Portal_Controller::class);
$portal_controller->register();

$customer_user_sync = $myvh_container->get(MYVH_Customer_User_Sync::class);
$customer_user_sync->register();

return $myvh_container;
