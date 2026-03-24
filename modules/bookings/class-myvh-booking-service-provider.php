<?php

require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-addon-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-discount-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-charge-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-save-booking-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-request-validator.php';

require_once MYVH_PLUGIN_DIR . 'modules/availability/class-myvh-availability-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-validator.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-room-rules-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-pricing-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-controller.php';

class MYVH_Booking_Service_Provider
{
    public function register($container): void {
        $container->singleton(MYVH_Booking_Repository::class);
        $container->singleton(MYVH_Booking_Addon_Repository::class);
        $container->singleton(MYVH_Booking_Discount_Repository::class);
        $container->singleton(MYVH_Booking_Charge_Repository::class);

        $container->singleton(MYVH_Availability_Service::class);
        $container->singleton(MYVH_Booking_Validator::class);
        $container->singleton(MYVH_Booking_Request_Validator::class);
        $container->singleton(MYVH_Room_Rules_Service::class);
        $container->singleton(MYVH_Pricing_Service::class);

        $container->singleton(MYVH_Booking_Service::class, function($container) {
            return new MYVH_Booking_Service(
                $container->get(MYVH_Room_Service::class),
                $container->get(MYVH_Booking_Repository::class),
                $container->get(MYVH_Booking_Charge_Repository::class),
                $container->get(MYVH_Booking_Addon_Repository::class),
                $container->get(MYVH_Addon_Repository::class),
                $container->get(MYVH_Addon_Service::class),
                $container->get(MYVH_Booking_Validator::class),
                $container->get(MYVH_Availability_Service::class),
                $container->get(MYVH_Room_Rules_Service::class),
                $container->get(MYVH_Pricing_Service::class),
                $container->get(MYVH_Customer_Repository::class),
                $container->get(MYVH_Organisation_Repository::class),
                $container->get(MYVH_Recurring_Pattern_Service::class)
            );
        });
        $container->singleton(MYVH_Booking_Controller::class);
    }
}