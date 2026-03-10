<?php

// ── Controllers ───────────────────────────────────────────────────────────────
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/customer-groups/class-myvh-customer-group-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/venues/class-myvh-venue-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-room-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-room-rate-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/addons/class-myvh-addon-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/customers/class-myvh-customer-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-invoice-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-recurring-pattern-controller.php';

global $myvh_container;

$myvh_container->singleton(MYVH_Booking_Controller::class);

$myvh_container->singleton(MYVH_Customer_Group_Controller::class);

$myvh_container->singleton(MYVH_Venue_Controller::class);

$myvh_container->singleton(MYVH_Room_Controller::class);

$myvh_container->singleton(MYVH_Room_Rate_Controller::class);

$myvh_container->singleton(MYVH_Addon_Controller::class);

$myvh_container->singleton(MYVH_Customer_Controller::class);

$myvh_container->singleton(MYVH_Invoice_Controller::class);

$myvh_container->singleton(MYVH_Recurring_Pattern_Controller::class);

