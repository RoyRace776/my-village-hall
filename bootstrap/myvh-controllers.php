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
require_once MYVH_PLUGIN_DIR . 'modules/venues/VenueController.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-room-controller.php';

// ── Customers ────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/customers/class-myvh-customer-controller.php';

// ── Organisations ─────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-organisation-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-organisation-type-controller.php';

// ── Pricing ───────────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-room-rate-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/addons/AddonController.php';

// ── Bookings ──────────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/bookings/BookingController.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/RecurringPatternController.php';

// ── Invoices & Payments ───────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-invoice-controller.php';

// ── Calendars ───────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/calendar/class-myvh-calendar-ajax-controller.php';

