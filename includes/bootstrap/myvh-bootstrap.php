<?php
/**
 * Bootstrap — wire all services and controllers, register in MYVH_Registry.
 *
 * No global variables are used. Everything is retrieved via MYVH_Registry::get()
 * and stored back with MYVH_Registry::set().
 */

// ── Controllers ───────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-booking-controller.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-customer-group-controller.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-venue-controller.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-room-controller.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-room-rate-controller.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-addon-controller.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-customer-controller.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-invoice-controller.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-recurring-pattern-controller.php';

// ── Services ──────────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-venue-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-room-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-customer-group-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-room-rate-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-booking-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-addon-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-customer-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-invoice-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-recurring-pattern-service.php';

// ── Domain services ───────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'includes/domain/booking/class-myvh-booking-validator.php';
require_once MYVH_PLUGIN_DIR . 'includes/domain/booking/class-myvh-availability-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/domain/booking/class-myvh-pricing-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/domain/room/class-myvh-room-rules-service.php';

// ── Venue ─────────────────────────────────────────────────────────────────────
$venue_service = new MYVH_Venue_Service(MYVH_Registry::get('venue_repo'));
MYVH_Registry::set('venue_service',     $venue_service);
MYVH_Registry::set('venue_controller',  new MYVH_Venue_Controller($venue_service));

// ── Room ──────────────────────────────────────────────────────────────────────
$room_service = new MYVH_Room_Service(MYVH_Registry::get('room_repo'));
MYVH_Registry::set('room_service',     $room_service);
MYVH_Registry::set('room_controller',  new MYVH_Room_Controller($room_service));

// ── Customer Group ────────────────────────────────────────────────────────────
$customer_group_service = new MYVH_Customer_Group_Service(MYVH_Registry::get('customer_group_repo'));
MYVH_Registry::set('customer_group_service',    $customer_group_service);
MYVH_Registry::set('customer_group_controller', new MYVH_Customer_Group_Controller($customer_group_service));

// ── Room Rate ─────────────────────────────────────────────────────────────────
$room_rate_service = new MYVH_Room_Rate_Service(MYVH_Registry::get('room_rate_repo'));
MYVH_Registry::set('room_rate_service',    $room_rate_service);
MYVH_Registry::set('room_rate_controller', new MYVH_Room_Rate_Controller($room_rate_service));

// ── Booking (depends on several domain services) ──────────────────────────────
$booking_validator    = new MYVH_Booking_Validator();
$availability_service = new MYVH_Availability_Service(MYVH_Registry::get('booking_repo'));
$room_rules_service   = new MYVH_Room_Rules_Service();
$pricing_service      = new MYVH_Pricing_service(MYVH_Registry::get('room_rate_repo'));

MYVH_Registry::set('booking_validator',    $booking_validator);
MYVH_Registry::set('availability_service', $availability_service);
MYVH_Registry::set('room_rules_service',   $room_rules_service);
MYVH_Registry::set('pricing_service',      $pricing_service);

$booking_service = new MYVH_Booking_Service(
    $room_service,
    MYVH_Registry::get('booking_repo'),
    MYVH_Registry::get('booking_addon_repo'),
    $booking_validator,
    $availability_service,
    $room_rules_service,
    $pricing_service
);
MYVH_Registry::set('booking_service',    $booking_service);
MYVH_Registry::set('booking_controller', new MYVH_Booking_Controller($booking_service));

// ── Add-on ────────────────────────────────────────────────────────────────────
$addon_service = new MYVH_Addon_Service(MYVH_Registry::get('addon_repo'));
MYVH_Registry::set('addon_service',    $addon_service);
MYVH_Registry::set('addon_controller', new MYVH_Addon_Controller($addon_service));

// ── Customer ──────────────────────────────────────────────────────────────────
$customer_service = new MYVH_Customer_Service(
    MYVH_Registry::get('customer_repo'),
    MYVH_Registry::get('booking_repo')
);
MYVH_Registry::set('customer_service',    $customer_service);
MYVH_Registry::set('customer_controller', new MYVH_Customer_Controller($customer_service));

// ── Invoice ───────────────────────────────────────────────────────────────────
$invoice_service = new MYVH_Invoice_Service(
    MYVH_Registry::get('invoice_repo'),
    MYVH_Registry::get('payment_repo')
);
MYVH_Registry::set('invoice_service',    $invoice_service);
MYVH_Registry::set('invoice_controller', new MYVH_Invoice_Controller($invoice_service));

// ── Recurring Pattern ─────────────────────────────────────────────────────────
$recurring_pattern_service = new MYVH_Recurring_Pattern_Service(
    MYVH_Registry::get('recurring_pattern_repo'),
    MYVH_Registry::get('booking_repo')
);

// Inject the recurring pattern service into booking service
// (needed when a new booking creates a pattern)
$booking_service->set_recurring_pattern_service($recurring_pattern_service);

MYVH_Registry::set('recurring_pattern_service',    $recurring_pattern_service);
MYVH_Registry::set('recurring_pattern_controller', new MYVH_Recurring_Pattern_Controller($recurring_pattern_service));

// ── Listeners ─────────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'includes/listeners/create-invoice-listener.php';
(new MYVH_Create_Invoice_Listener())->register();
