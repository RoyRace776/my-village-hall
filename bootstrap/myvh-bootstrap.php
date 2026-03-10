<?php
/**
 * Bootstrap
 *
 * No global variables are used. Everything is retrieved via $myvh_container->get()
 * and stored back with $myvh_container->set().
 */


// ── Services ──────────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/venues/class-myvh-venue-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-room-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/customer-groups/class-myvh-customer-group-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-room-rate-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/addons/class-myvh-addon-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/customers/class-myvh-customer-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-invoice-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-recurring-pattern-service.php';

// ── Domain services ───────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-validator.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-availability-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-pricing-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-room-rules-service.php';


// ── Listeners ─────────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/events/class-myvh-booking-listener.php';
(new MYVH_Booking_Listener())->register();
