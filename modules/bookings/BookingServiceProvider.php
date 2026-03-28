<?php

require_once MYVH_PLUGIN_DIR . 'modules/bookings/BookingRepository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/BookingAddonRepository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/BookingDiscountRepository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/BookingChargeRepository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/SaveBookingRequest.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-request-validator.php';

require_once MYVH_PLUGIN_DIR . 'modules/availability/AvailabilityService.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/BookingValidator.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-room-rules-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-pricing-service.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/BookingService.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/BookingController.php';

class BookingServiceProvider
{
    public function register($container): void {
        $container->singleton(BookingRepository::class);
        $container->singleton(BookingAddonRepository::class);
        $container->singleton(BookingDiscountRepository::class);
        $container->singleton(BookingChargeRepository::class);

        $container->singleton(AvailabilityService::class);
        $container->singleton(BookingValidator::class);
        $container->singleton(BookingRequestValidator::class);
        $container->singleton(RoomRulesService::class);
        $container->singleton(PricingService::class);

        $container->singleton(BookingService::class, function($container) {
            return new BookingService(
                $container->get(RoomService::class),
                $container->get(BookingRepository::class),
                $container->get(BookingChargeRepository::class),
                $container->get(BookingAddonRepository::class),
                $container->get(AddonRepository::class),
                $container->get(AddonService::class),
                $container->get(BookingValidator::class),
                $container->get(AvailabilityService::class),
                $container->get(RoomRulesService::class),
                $container->get(PricingService::class),
                $container->get(CustomerRepository::class),
                $container->get(OrganisationRepository::class),
                $container->get(RecurringPatternService::class)
            );
        });
        $container->singleton(BookingController::class);
    }
}