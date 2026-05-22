<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

use MYVH\Subscriptions\Entities\BillingPeriod;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Repositories\UsageRepository;

if (!defined('ABSPATH')) {
    exit;
}

class UsageService {
    private const METRIC_BOOKINGS = 'bookings';

    public function __construct(
        private UsageRepository $usage_repository,
        private SubscriptionRepository $subscription_repository,
        private PlanRepository $plan_repository
    ) {
    }

    public function recordBooking(int $account_id): bool {
        if ($account_id <= 0) {
            return false;
        }

        $period = $this->resolveCurrentBillingPeriod($account_id);

        return $this->usage_repository->increment_quantity_for_period(
            $account_id,
            self::METRIC_BOOKINGS,
            $period->getStart(),
            $period->getEnd()
        );
    }

    public function getCurrentUsage(int $account_id): int {
        if ($account_id <= 0) {
            return 0;
        }

        $period = $this->resolveCurrentBillingPeriod($account_id);

        return $this->usage_repository->get_quantity_for_period(
            $account_id,
            self::METRIC_BOOKINGS,
            $period->getStart(),
            $period->getEnd()
        );
    }

    public function pruneHistoricalUsage(int $months_to_keep = 3): int {
        $months_to_keep = max(1, $months_to_keep);
        $cutoff_month = gmdate('Y-m', strtotime(sprintf('first day of -%d month', $months_to_keep - 1)));

        return $this->usage_repository->prune_before_month($cutoff_month);
    }

    public function exceedsLimit(int $account_id): bool {
        if ($account_id <= 0) {
            return false;
        }

        $subscription = $this->subscription_repository->get_active_by_account_id($account_id);
        if (!$subscription instanceof Subscription) {
            return false;
        }

        $plan = $this->resolve_plan($subscription);
        if (!$plan instanceof Plan) {
            return false;
        }

        $booking_limit = $plan->getBookingLimit();
        if ($booking_limit === null) {
            return false;
        }

        return $this->getCurrentUsage($account_id) >= $booking_limit;
    }

    private function resolve_plan(Subscription $subscription): ?Plan {
        $plan_id = $subscription->getPlanId();
        if ($plan_id > 0) {
            $plan = $this->plan_repository->get_by_id($plan_id);

            return $plan instanceof Plan ? $plan : null;
        }

        $plan_code = $subscription->getPlanCode();
        if ($plan_code === '') {
            return null;
        }

        return $this->plan_repository->getByCode($plan_code);
    }

    private function resolveCurrentBillingPeriod(int $account_id): BillingPeriod {
        $subscription = $this->subscription_repository->get_active_by_account_id($account_id);
        if ($subscription instanceof Subscription) {
            $period = BillingPeriod::fromSubscription($subscription);
            if ($period instanceof BillingPeriod) {
                return $period;
            }
        }

        return BillingPeriod::currentCalendarMonth();
    }
}
