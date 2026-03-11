<?php
/**
 * Service class definitions
 *
 * Requires (loads) every service class file so the classes are available
 * before the DI container is built in myvh-container.php.
 *
 * Services contain the business logic of the plugin.
 * They are instantiated and wired by the service providers,
 * not here — this file is purely for autoloading class files.
 *
 * Load order mirrors the dependency graph:
 *   1. Venues & Rooms       — no dependencies on other services
 *   2. Customers & Groups   — no dependencies on other services
 *   3. Pricing              — depends on rooms and customer groups
 *   4. Bookings             — depends on rooms, customers, and pricing
 *   5. Invoices             — depends on bookings and customers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── 1. Venues & Rooms ─────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/venues/class-myvh-venue-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-room-service.php';

// ── 2. Customers & Groups ─────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/customers/class-myvh-customer-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/customer-groups/class-myvh-customer-group-service.php';

// ── 3. Pricing ────────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-room-rate-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-pricing-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/addons/class-myvh-addon-service.php';

// ── 4. Bookings ───────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/availability/class-myvh-availability-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-validator.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-recurring-pattern-service.php';

// ── 5. Invoices ───────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-invoice-service.php';
