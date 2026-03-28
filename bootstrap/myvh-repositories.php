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
 *   2. Customers            — who makes bookings
 *   3. Bookings             — the core booking records
 *   4. Pricing              — rates, add-ons, discounts
 *   5. Invoices & Payments  — billing
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once MYVH_PLUGIN_DIR . '/core/support/RepositoryBase.php';

// ── 1. Venues & Rooms ─────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/venues/VenueRepository.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-room-repository.php';

// ── 2. Customers ─────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/customers/class-myvh-customer-repository.php';

// ── 3. Organisations ──────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-organisation-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-organisation-type-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-organisation-member-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-organisation-member-request-repository.php';

// ── 3. Bookings ───────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/bookings/BookingRepository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/RecurringPatternRepository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/BookingAddonRepository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/BookingChargeRepository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/BookingDiscountRepository.php';

// ── 4. Pricing ────────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-room-rate-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/addons/AddonRepository.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-discount-repository.php';

// ── 5. Invoices & Payments ────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-invoice-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-invoice-item-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/payments/class-myvh-payment-repository.php';
