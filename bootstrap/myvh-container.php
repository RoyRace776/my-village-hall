<?php
/**
 * DI Container setup
 *
 * Builds the Container instance, registers all service providers,
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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
global $myvh_container;

use MYVH\Container\Container;

$myvh_container = new Container();

// ── Require service-provider class files ──────────────────────────────────────
// Each provider is a single class that registers its module's bindings.

require_once MYVH_PLUGIN_DIR . 'modules/venues/class-myvh-venue-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-room-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/customers/class-myvh-customer-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-organisation-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-pricing-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/addons/class-myvh-addon-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/availability/class-myvh-availability-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-invoice-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/payments/class-myvh-payment-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/calendar/class-myvh-calendar-service-provider.php';
require_once MYVH_PLUGIN_DIR . 'modules/portal/class-myvh-portal-service-provider.php';


// Register wpdb so it can be injected like any other dependency
$myvh_container->singleton( \wpdb::class, function () {
    global $wpdb;
    return $wpdb;
} );

// ── Register providers with the container ─────────────────────────────────────
// Order follows the dependency graph (no provider should depend on a later one).

$providers = [
    VenueServiceProvider::class,
    RoomServiceProvider::class,
    CustomerServiceProvider::class,
    OrganisationServiceProvider::class,
    PricingServiceProvider::class,
    AddonServiceProvider::class,
    BookingServiceProvider::class,
    AvailabilityServiceProvider::class,
    InvoiceServiceProvider::class,
    PaymentServiceProvider::class,
    CalendarServiceProvider::class,
    PortalServiceProvider::class,
];

foreach ( $providers as $provider ) {
    ( new $provider() )->register( $myvh_container );
}

return $myvh_container;
