<?php
/**
 * Repository class definitions
 *
 * Requires (loads) every repository class file so the classes are available
 * before the DI container is built in myvh-container.php.
 *
 * Repositories are responsible for all direct database access.
 * They are instantiated and wired together by the service providers,
 * not here — this file is purely for autoloading class files.
 *
 * Load order follows domain groupings:
 *   1. Venues & Rooms       — physical locations
 *   2. Customers & Groups   — who makes bookings
 *   3. Bookings             — the core booking records
 *   4. Pricing              — rates, add-ons, discounts
 *   5. Invoices & Payments  — billing
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── 1. Venues & Rooms ─────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/venues/class-venue-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-room-repository.php';

// ── 2. Customers & Groups ─────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/customers/class-customer-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/customer-groups/class-customer-group-repository.php';

// ── 3. Bookings ───────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-recurring-pattern-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-addon-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-charge-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-discount-repository.php';

// ── 4. Pricing ────────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-room-rate-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/addons/class-addon-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-discount-repository.php';

// ── 5. Invoices & Payments ────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-invoice-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-invoice-item-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/payments/class-payment-repository.php';
