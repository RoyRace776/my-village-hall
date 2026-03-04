<?php
// Include controller classes
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-customer-group-controller.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-venue-controller.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-room-controller.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-room-rate-controller.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-booking-controller.php';

// Include service classes
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-venue-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-room-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-customer-group-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-room-rate-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-booking-service.php';

require_once MYVH_PLUGIN_DIR . 'includes/domain/booking/class-myvh-booking-validator.php';
require_once MYVH_PLUGIN_DIR . 'includes/domain/booking/class-myvh-availability-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/domain/booking/class-myvh-pricing-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/domain/room/class-myvh-room-rules-service.php';

// Get the repos ready. These get initialised when they're included
global $myvh_venue_repo;
global $myvh_room_repo;
global $myvh_customer_group_repo;
global $myvh_room_rate_repo;
global $myvh_booking_repo;
global $myvh_addon_repo;
global $myvh_customer_repo;
global $myvh_invoice_repo;
global $myvh_recurring_pattern_repo;

// Now create the domain services
global $myvh_booking_validator;
global $myvh_booking_availability;
global $myvh_room_rules;
global $myvh_pricing_service;

$myvh_booking_validator = new MYVH_Booking_Validator();
$myvh_availability_service = new MYVH_Availability_Service($myvh_booking_repo);
$myvh_room_rules_service = new MYVH_Room_Rules_Service();
$myvh_pricing_service = new MYVH_Pricing_service($myvh_room_rate_repo);


// Now create the services and controllers
global $myvh_venue_service;
global $myvh_venue_controller;


$myvh_venue_service    = new MYVH_Venue_Service($myvh_venue_repo);
$myvh_venue_controller = new MYVH_Venue_Controller($myvh_venue_service);

global $myvh_room_service;
global $myvh_room_controller;

$myvh_room_service    = new MYVH_Room_Service($myvh_room_repo);
$myvh_room_controller = new MYVH_Room_Controller($myvh_room_service);

global $myvh_customer_group_service;
global $myvh_customer_group_controller;

$myvh_customer_group_service    = new MYVH_Customer_Group_Service($myvh_customer_group_repo);
$myvh_customer_group_controller = new MYVH_Customer_Group_Controller($myvh_customer_group_service);

global $myvh_room_rate_service;
global $myvh_room_rate_controller;

$myvh_room_rate_service    = new MYVH_Room_Rate_Service($myvh_room_rate_repo);
$myvh_room_rate_controller = new MYVH_Room_Rate_Controller($myvh_room_rate_service);

global $myvh_booking_service;
global $myvh_booking_controller;

$myvh_booking_service = new MYVH_Booking_Service($myvh_room_service, $myvh_booking_repo, $myvh_booking_validator,
                                                 $myvh_booking_availability, $myvh_room_rules, $myvh_pricing_service);
$myvh_booking_controller = new MYVH_Booking_Controller($myvh_booking_service);

// ==========================================
// ADDON MANAGEMENT
// ==========================================
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-addon-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-addon-controller.php';

global $myvh_addon_service;
global $myvh_addon_controller;

$myvh_addon_service    = new MYVH_Addon_Service($myvh_addon_repo);
$myvh_addon_controller = new MYVH_Addon_Controller($myvh_addon_service);

// ==========================================
// CUSTOMER MANAGEMENT
// ==========================================
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-customer-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-customer-controller.php';

global $myvh_customer_service;
global $myvh_customer_controller;

$myvh_customer_service    = new MYVH_Customer_Service($myvh_customer_repo);
$myvh_customer_controller = new MYVH_Customer_Controller($myvh_customer_service);

// ==========================================
// INVOICE MANAGEMENT
// ==========================================
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-invoice-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-invoice-controller.php';

global $myvh_invoice_service;
global $myvh_invoice_controller;

$myvh_invoice_service    = new MYVH_Invoice_Service($myvh_invoice_repo);
$myvh_invoice_controller = new MYVH_Invoice_Controller($myvh_invoice_service);

// ==========================================
// RECURRING PATTERN MANAGEMENT
// ==========================================
require_once MYVH_PLUGIN_DIR . 'includes/services/class-myvh-recurring-pattern-service.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/controllers/class-myvh-recurring-pattern-controller.php';

global $myvh_recurring_pattern_service;
global $myvh_recurring_pattern_controller;

$myvh_recurring_pattern_service    = new MYVH_Recurring_Pattern_Service($myvh_recurring_pattern_repo);
$myvh_recurring_pattern_controller = new MYVH_Recurring_Pattern_Controller($myvh_recurring_pattern_service);

// Set up listeners
require_once MYVH_PLUGIN_DIR . 'includes/listeners/create-invoice-listener.php';
$invoice_listener = new MYVH_Create_Invoice_Listener();
$invoice_listener->register();