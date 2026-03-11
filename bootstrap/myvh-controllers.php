<?php
/**
 * Controller class definitions
 *
 * Requires (loads) every controller class file so the classes are available
 * when admin_post_{action} hooks fire.
 *
 * Controllers handle HTTP-level concerns: reading form input, calling the
 * appropriate service, and redirecting with an admin notice.
 * They contain no business logic themselves.
 *
 * Load order mirrors the domain groupings used in myvh-repositories.php
 * and myvh-services.php for consistency.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Venues & Rooms ────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/venues/class-myvh-venue-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-room-controller.php';

// ── Customers & Groups ────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/customers/class-myvh-customer-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/customer-groups/class-myvh-customer-group-controller.php';

// ── Pricing ───────────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-room-rate-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/addons/class-myvh-addon-controller.php';

// ── Bookings ──────────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-recurring-pattern-controller.php';

// ── Invoices & Payments ───────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-invoice-controller.php';
