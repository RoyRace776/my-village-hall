<?php

namespace MYVH\Bookings;

class BookingServiceProvider
{
    public function register($container): void
    {
        $container->singleton(BookingRepository::class);
        $container->singleton(BookingAddonRepository::class);
        $container->singleton(BookingDiscountRepository::class);
        $container->singleton(BookingChargeRepository::class);

        $container->singleton(\MYVH\Availability\AvailabilityService::class);
        $container->singleton(BookingValidator::class);
        $container->singleton(BookingRequestValidator::class);
        $container->singleton(\MYVH\Rooms\RoomRulesService::class);
        $container->singleton(\MYVH\Pricing\PricingService::class);

        $container->singleton(BookingService::class, function($container) {
            return new BookingService(
                $container->get(\MYVH\Rooms\RoomService::class),
                $container->get(BookingRepository::class),
                $container->get(BookingChargeRepository::class),
                $container->get(BookingAddonRepository::class),
                $container->get(\MYVH\Addons\AddonRepository::class),
                $container->get(\MYVH\Addons\AddonService::class),
                $container->get(BookingValidator::class),
                $container->get(\MYVH\Availability\AvailabilityService::class),
                $container->get(\MYVH\Rooms\RoomRulesService::class),
                $container->get(\MYVH\Pricing\PricingService::class),
                $container->get(\MYVH\Customers\CustomerRepository::class),
                $container->get(\MYVH\Organisations\OrganisationRepository::class),
                $container->get(RecurringPatternService::class)
            );
        });
        $container->singleton(BookingController::class);
    }
}
