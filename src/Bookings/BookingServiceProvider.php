<?php

namespace MYVH\Bookings;

use MYVH\Availability\AvailabilityService;
use MYVH\Rooms\RoomRulesService;
use MYVH\Pricing\PricingService;
use MYVH\Customers\CustomerRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Bookings\Services\BookingAutoConfirm;
use MYVH\Rooms\RoomService;
use MYVH\Addons\AddonRepository;
use MYVH\Addons\AddonService;


class BookingServiceProvider
{
    public function register($container): void
    {
        $container->singleton(BookingRepository::class);
        $container->singleton(BookingAddonRepository::class);
        $container->singleton(BookingDiscountRepository::class);
        $container->singleton(BookingChargeRepository::class);

        $container->singleton(AvailabilityService::class);
        $container->singleton(BookingValidator::class);
        $container->singleton(BookingRequestValidator::class);
        $container->singleton(RoomRulesService::class);
        $container->singleton(PricingService::class);

        $container->singleton(BookingService::class);
        $container->singleton(BookingStatus::class);
        $container->singleton(BookingController::class);
        $container->singleton(BookingAutoConfirm::class);
    }
}
