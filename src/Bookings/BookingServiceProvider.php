<?php

namespace MYVH\Bookings;

use MYVH\Availability\AvailabilityService;
use MYVH\Container\Container;
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
use MYVH\Deposits\DepositService;
use MYVH\Rooms\RoomService;
use MYVH\Addons\AddonRepository;
use MYVH\Addons\AddonService;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\ProcessedStripeEventRepository;
use MYVH\Subscriptions\Repositories\SettingsRepository;
use MYVH\Subscriptions\Repositories\SubscriptionEventLogRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Repositories\UsageRepository;
use MYVH\Subscriptions\Services\AccountService;
use MYVH\Subscriptions\Services\BillingService;
use MYVH\Subscriptions\Services\BillingNotificationService;
use MYVH\Subscriptions\Services\FeatureGate;
use MYVH\Subscriptions\Services\FeatureService;
use MYVH\Subscriptions\Services\ManualInvoiceService;
use MYVH\Subscriptions\Services\PlanService;
use MYVH\Subscriptions\Services\SettingsService;
use MYVH\Subscriptions\Services\StripeService;
use MYVH\Subscriptions\Services\SubscriptionEventLogger;
use MYVH\Subscriptions\Services\SubscriptionGuard;
use MYVH\Subscriptions\Services\SubscriptionLifecycleScheduler;
use MYVH\Subscriptions\Services\SubscriptionLifecycleService;
use MYVH\Subscriptions\Services\TrialService;
use MYVH\Subscriptions\Services\UsageService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;


class BookingServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(BookingRepository::class, function ($container) {
            $wpdb = $container->get(\wpdb::class);
            try {
                $logger = $container->get(LoggerInterface::class);
            } catch (\Exception $e) {
                $logger = new NullLogger();
            }
            $repository = new BookingRepository(
                $wpdb,
                $logger
            );

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
        $container->singleton(AccountRepository::class);
        $container->singleton(PlanRepository::class);
        $container->singleton(ProcessedStripeEventRepository::class);
        $container->singleton(SettingsRepository::class);
        $container->singleton(SubscriptionEventLogRepository::class);
        $container->singleton(SubscriptionRepository::class);
        $container->singleton(UsageRepository::class);
        $container->singleton(AccountService::class);
        $container->singleton(BillingNotificationService::class);
        $container->singleton(ManualInvoiceService::class);
        $container->singleton(StripeService::class);
        $container->singleton(BillingService::class);
        $container->singleton(TrialService::class);
        $container->singleton(SettingsService::class);
        $container->singleton(PlanService::class);
        $container->singleton(FeatureGate::class);
        $container->singleton(FeatureService::class);
        $container->singleton(SubscriptionEventLogger::class);
        $container->singleton(SubscriptionLifecycleService::class);
        $container->singleton(SubscriptionLifecycleScheduler::class);
        $container->singleton(UsageService::class);
        $container->singleton(SubscriptionGuard::class);

        $container->singleton(BookingService::class, function ($container) {
            try {
                $logger = $container->get(LoggerInterface::class);
            } catch (\Exception $e) {
                $logger = new NullLogger();
            }

            return new BookingService(
                $container->get(RoomService::class),
                $container->get(BookingRepository::class),
                $container->get(BookingAddonRepository::class),
                $container->get(AddonService::class),
                $container->get(BookingValidator::class),
                $container->get(BookingAddonSyncService::class),
                $container->get(BookingChargeService::class),
                $container->get(BookingChargeableHoursCalculator::class),
                $container->get(BookingCreationEventDispatcher::class),
                $container->get(BookingDeletionService::class),
                $container->get(BookingLifecycleEventDispatcher::class),
                $container->get(BookingAccessControl::class),
                $container->get(BookingMovementService::class),
                $container->get(BookingQueryService::class),
                $container->get(BookingStatusTransitionDispatcher::class),
                $container->get(BookingUpdateEventDispatcher::class),
                $container->get(RecurringPatternService::class),
                $container->get(RecurringBookingCreator::class),
                $container->get(RecurringBookingUpdater::class),
                $container->get(\MYVH\Invoices\InvoiceService::class),
                $container->get(\MYVH\Invoices\InvoiceItemRepository::class),
                $container->get(BookingChargeRepository::class),
                $container->get(DepositService::class),
                $logger,
                $container->get(SubscriptionGuard::class),
                $container->get(FeatureService::class),
                $container->get(UsageService::class),
                $container->get(AccountService::class)
            );
        });
        $container->singleton(BookingController::class);
        $container->singleton(BookingAutoConfirm::class);
        $container->singleton(BookingAddonSyncService::class);
        $container->singleton(BookingChargeService::class);
        $container->singleton(RecurringPatternService::class, function ($container) {
            try {
                $logger = $container->get(LoggerInterface::class);
            } catch (\Exception $e) {
                $logger = new NullLogger();
            }

            return new RecurringPatternService(
                $container->get(RecurringPatternRepository::class),
                $container->get(BookingRepository::class),
                $container->get(BookingChargeService::class),
                $container->get(SubscriptionGuard::class),
                $container->get(UsageService::class),
                $container->get(AccountService::class),
                $logger
            );
        });
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
        $container->singleton(DepositService::class);
    }
}
