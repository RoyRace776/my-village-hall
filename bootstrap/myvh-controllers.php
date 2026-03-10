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

$myvh_container->bind('booking_controller', function($c) {
    return new MYVH_Booking_Controller(
        $c->get('booking_service')
    );
});

$myvh_container->bind('customer_group_controller', function($c) {
    return new MYVH_Customer_Group_Controller(
        $c->get('customer_group_service')
    );
});

$myvh_container->bind('venue_controller', function($c) {
    return new MYVH_Venue_Controller(
        $c->get('venue_service')
    );
});

$myvh_container->bind('room_controller', function($c) {
    return new MYVH_Room_Controller(
        $c->get('room_service')
    );
});

$myvh_container->bind('room_rate_controller', function($c) {
    return new MYVH_Room_Rate_Controller(
        $c->get('room_rate_service')
    );
});

$myvh_container->bind('addon_controller', function($c) {
    return new MYVH_Addon_Controller(
        $c->get('addon_service')
    );
});

$myvh_container->bind('customer_controller', function($c) {
    return new MYVH_Customer_Controller(
        $c->get('customer_service')
    );
});

$myvh_container->bind('invoice_controller', function($c) {
    return new MYVH_Invoice_Controller(
        $c->get('invoice_service')
    );
});

$myvh_container->bind('recurring_pattern_controller', function($c) {
    return new MYVH_Recurring_Pattern_Controller(
        $c->get('recurring_pattern_service')
    );
});

