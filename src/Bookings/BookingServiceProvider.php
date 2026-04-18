<?php

namespace MYVH\Bookings;

use MYVH\Availability\AvailabilityService;
use MYVH\Rooms\RoomRulesService;
use MYVH\Pricing\PricingService;
use MYVH\Customers\CustomerRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Bookings\Services\BookingAutoConfirm;
use MYVH\Bookings\Services\BookingAccessControl;
use MYVH\Bookings\Services\BookingAddonSyncService;
use MYVH\Bookings\Services\BookingChargeService;
use MYVH\Bookings\Services\BookingChargeableHoursCalculator;
use MYVH\Bookings\Services\BookingCreationEventDispatcher;
use MYVH\Bookings\Services\BookingDeletionService;
use MYVH\Bookings\Services\BookingLifecycleEventDispatcher;
use MYVH\Bookings\Services\BookingListGroupingService;
use MYVH\Bookings\Services\BookingMovementService;
use MYVH\Bookings\Services\BookingQueryService;
use MYVH\Bookings\Services\BookingStatusTransitionDispatcher;
use MYVH\Bookings\Services\BookingUpdateEventDispatcher;
use MYVH\Bookings\Services\RecurringBookingCreator;
use MYVH\Bookings\Services\RecurringBookingUpdater;
use MYVH\Rooms\RoomService;
use MYVH\Addons\AddonRepository;
use MYVH\Addons\AddonService;


class BookingServiceProvider
{
    public function register($container): void
    {
        $container->singleton(BookingRepository::class, function ($container) {
            $wpdb = $container->get(\wpdb::class);
            $repository = new BookingRepository($wpdb);

            return new CachedBookingRepository($repository);
        });
        $container->singleton(BookingAddonRepository::class);
        $container->singleton(BookingDiscountRepository::class);
        $container->singleton(BookingChargeRepository::class);

        $container->singleton(AvailabilityService::class);
        $container->singleton(BookingValidator::class);
        $container->singleton(BookingRequestValidator::class);
        $container->singleton(RoomRulesService::class);
        $container->singleton(PricingService::class);

        $container->singleton(BookingService::class);
        $container->singleton(BookingController::class);
        $container->singleton(BookingAutoConfirm::class);
        $container->singleton(BookingAddonSyncService::class);
        $container->singleton(BookingChargeService::class);
        $container->singleton(BookingChargeableHoursCalculator::class);
        $container->singleton(BookingCreationEventDispatcher::class);
        $container->singleton(BookingDeletionService::class);
        $container->singleton(BookingLifecycleEventDispatcher::class);
        $container->singleton(BookingAccessControl::class);
        $container->singleton(BookingListGroupingService::class);
        $container->singleton(BookingMovementService::class);
        $container->singleton(BookingQueryService::class);
        $container->singleton(BookingStatusTransitionDispatcher::class);
        $container->singleton(BookingUpdateEventDispatcher::class);
        $container->singleton(RecurringBookingCreator::class);
        $container->singleton(RecurringBookingUpdater::class);
    }
}
