<?php

global $wpdb;
global $myvh_container;

$myvh_container = new MYVH_Container();

require MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-service-provider.php';
require MYVH_PLUGIN_DIR . 'modules/addons/class-addon-service-provider.php';
require MYVH_PLUGIN_DIR . 'modules/customer-groups/class-customer-group-service-provider.php';
require MYVH_PLUGIN_DIR . 'modules/customers/class-customer-service-provider.php';
require MYVH_PLUGIN_DIR . 'modules/invoices/class-invoice-service-provider.php';
require MYVH_PLUGIN_DIR . 'modules/payments/class-payment-service-provider.php';
require MYVH_PLUGIN_DIR . 'modules/pricing/class-pricing-service-provider.php';
require MYVH_PLUGIN_DIR . 'modules/rooms/class-room-service-provider.php';
require MYVH_PLUGIN_DIR . 'modules/venues/class-venue-service-provider.php';

$providers = [
    MYVH_Booking_Service_Provider::class,
    MYVH_Addon_Service_Provider::class,
    MYVH_Customer_Group_Service_Provider::class,
    MYVH_Customer_Service_Provider::class,
    MYVH_Invoice_Service_Provider::class,
    MYVH_Payment_Service_Provider::class,
    MYVH_Room_Service_Provider::class,
    MYVH_Venue_Service_Provider::class,
];

foreach ($providers as $provider) {
    (new $provider())->register($myvh_container);
}

return $myvh_container;