<?php

require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-addon-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-discount-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-charge-repository.php';

require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-availability-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-validator.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-room-rules-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-pricing-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-controller.php';

class MYVH_Booking_Service_Provider
{
    public function register($container)
    {
        $container->singleton(MYVH_Booking_Repository::class);
        $container->singleton(MYVH_Booking_Addon_Repository::class);
        $container->singleton(MYVH_Booking_Discount_Repository::class);
        $container->singleton(MYVH_Booking_Charge_Repository::class);

        $container->singleton(MYVH_Availability_Service::class);
        $container->singleton(MYVH_Booking_Validator::class);
        $container->singleton(MYVH_Room_Rules_Service::class);
        $container->singleton(MYVH_Pricing_Service::class);

        $container->singleton(MYVH_Booking_Service::class);
        $container->singleton(MYVH_Booking_Controller::class);
    }
}