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

class Booking_Service_Provider
{
    public function register($container): void {
        $container->singleton(Booking_Repository::class);
        $container->singleton(Booking_Addon_Repository::class);
        $container->singleton(Booking_Discount_Repository::class);
        $container->singleton(Booking_Charge_Repository::class);

        $container->singleton(Availability_Service::class);
        $container->singleton(Booking_Validator::class);
        $container->singleton(Booking_Request_Validator::class);
        $container->singleton(Room_Rules_Service::class);
        $container->singleton(Pricing_Service::class);

        $container->singleton(Booking_Service::class, function($container) {
            return new Booking_Service(
                $container->get(Room_Service::class),
                $container->get(Booking_Repository::class),
                $container->get(Booking_Charge_Repository::class),
                $container->get(Booking_Addon_Repository::class),
                $container->get(Addon_Repository::class),
                $container->get(Addon_Service::class),
                $container->get(Booking_Validator::class),
                $container->get(Availability_Service::class),
                $container->get(Room_Rules_Service::class),
                $container->get(Pricing_Service::class),
                $container->get(Customer_Repository::class),
                $container->get(Organisation_Repository::class),
                $container->get(Recurring_Pattern_Service::class)
            );
        });
        $container->singleton(Booking_Controller::class);
    }
}