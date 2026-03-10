<?php
/**
 * Instantiate all repositories
 *
 * Each repository file no longer self-registers into a global variable —
 * the two lines at the bottom of each class file (global $x; $x = new X();)
 * have been removed. All instances live here.
 */
global $myvh_container;

require_once MYVH_PLUGIN_DIR . 'modules/addons/class-addon-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-addon-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-charge-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-discount-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/customer-groups/class-customer-group-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/customers/class-customer-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-discount-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-invoice-item-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-invoice-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/payments/class-payment-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-recurring-pattern-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-room-rate-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-room-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/venues/class-venue-repository.php';

global $wpdb;

$myvh_container->singleton('wpdb', function () {
        global $wpdb;
        return $wpdb;
    }
);

$myvh_container->singleton('addon_repo', function($c) {
    global $wpdb;
    return new MYVH_Addon_Repository($wpdb);
});

$myvh_container->singleton('booking_addon_repo', function($c) {
    global $wpdb;
    return new MYVH_Booking_Addon_Repository($wpdb);
});

$myvh_container->singleton('booking_charge_repo', function($c) {
    global $wpdb;
    return new MYVH_Booking_Charge_Repository($wpdb);
});

$myvh_container->singleton('booking_discount_repo', function($c) {
    global $wpdb;
    return new MYVH_Booking_Discount_Repository($wpdb);
});

$myvh_container->singleton('booking_repo', function($c) {
    global $wpdb;
    return new MYVH_Booking_Repository($wpdb);
});

$myvh_container->singleton('customer_group_repo', function($c) {
    global $wpdb;
    return new MYVH_Customer_Group_Repository($wpdb);
});

$myvh_container->singleton('customer_repo', function($c) use ($wpdb) {
    return new MYVH_Customer_Repository($wpdb);
});

$myvh_container->singleton('discount_repo', function($c) {
    global $wpdb;
    return new MYVH_Discount_Repository($wpdb);
});

$myvh_container->singleton('invoice_item_repo', function($c) {
    global $wpdb;
    return new MYVH_Invoice_Item_Repository($wpdb);
});

$myvh_container->singleton('invoice_repo', function($c) {
    global $wpdb;
    return new MYVH_Invoice_Repository($wpdb);
});

$myvh_container->singleton('payment_repo', function($c) {
    global $wpdb;
    return new MYVH_Payment_Repository($wpdb);
});

$myvh_container->singleton('recurring_pattern_repo', function($c) {
    global $wpdb;
    return new MYVH_Recurring_Pattern_Repository($wpdb);
});

$myvh_container->singleton('room_rate_repo', function($c) {
    global $wpdb;
    return new MYVH_Room_Rate_Repository($wpdb);
});

$myvh_container->singleton('room_repo', function($c) {
    global $wpdb;
    return new MYVH_Room_Repository($wpdb);
});

$myvh_container->singleton('venue_repo', function($c) {
    global $wpdb;
    return new MYVH_Venue_Repository($wpdb);
});





//$myvh_container->set('addon_repo',              new MYVH_Addon_Repository($wpdb));
//$myvh_container->set('booking_addon_repo',      new MYVH_Booking_Addon_Repository($wpdb));
//$myvh_container->set('booking_charge_repo',     new MYVH_Booking_Charge_Repository($wpdb));
//$myvh_container->set('booking_discount_repo',   new MYVH_Booking_Discount_Repository($wpdb));
//$myvh_container->set('booking_repo',            new MYVH_Booking_Repository($wpdb));
//$myvh_container->set('customer_group_repo',     new MYVH_Customer_Group_Repository($wpdb));
//$myvh_container->set('customer_repo',           new MYVH_Customer_Repository($wpdb));
//$myvh_container->set('discount_repo',           new MYVH_Discount_Repository($wpdb));
//$myvh_container->set('invoice_item_repo',       new MYVH_Invoice_Item_Repository($wpdb));
//$myvh_container->set('invoice_repo',            new MYVH_Invoice_Repository($wpdb));
//$myvh_container->set('payment_repo',            new MYVH_Payment_Repository($wpdb));
//$myvh_container->set('recurring_pattern_repo',  new MYVH_Recurring_Pattern_Repository($wpdb));
//$myvh_container->set('room_rate_repo',          new MYVH_Room_Rate_Repository($wpdb));
//$myvh_container->set('room_repo',               new MYVH_Room_Repository($wpdb));
//$myvh_container->set('venue_repo',              new MYVH_Venue_Repository($wpdb));
