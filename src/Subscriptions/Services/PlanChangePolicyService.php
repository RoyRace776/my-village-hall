<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;

if (!defined('ABSPATH')) {
    exit;
}

class PlanChangePolicyService {
    private const TRIAL_PLAN_CODE = 'trial';

    public function __construct(
        private PlanRepository $plan_repository,
        private SubscriptionRepository $subscription_repository
    ) {
    }

    public function canChangeToPlan(int $account_id, Plan $target_plan): array {
        if ($account_id <= 0) {
            return [
                'allowed' => false,
                'is_downgrade' => false,
                'message' => __('Invalid account.', 'my-village-hall'),
            ];
        }

        $subscription = $this->subscription_repository->get_active_by_account_id($account_id);
        if (!$subscription instanceof Subscription) {
            return [
                'allowed' => true,
                'is_downgrade' => false,
                'message' => '',
            ];
        }

        $current_plan = $this->resolvePlan($subscription);
        if (!$current_plan instanceof Plan) {
            return [
                'allowed' => true,
                'is_downgrade' => false,
                'message' => '',
            ];
        }

        if ($target_plan->getCode() === $current_plan->getCode()) {
            return [
                'allowed' => false,
                'is_downgrade' => false,
                'message' => __('This plan is already active.', 'my-village-hall'),
            ];
        }

        $is_downgrade = $this->isDowngrade($current_plan, $target_plan);

        if (
            $is_downgrade
            && $target_plan->getCode() === self::TRIAL_PLAN_CODE
            && $current_plan->getCode() !== self::TRIAL_PLAN_CODE
        ) {
            return [
                'allowed' => false,
                'is_downgrade' => true,
                'message' => __('You cannot downgrade to the Trial plan.', 'my-village-hall'),
            ];
        }

        return [
            'allowed' => true,
            'is_downgrade' => $is_downgrade,
            'message' => '',
        ];
    }

    public function getPlanOptionsForAccount(int $account_id): array {
        $plans = $this->plan_repository->getAllActive();
        if ($account_id <= 0 || $plans === []) {
            return [];
        }

        $subscription = $this->subscription_repository->get_active_by_account_id($account_id);
        $current_plan = $subscription instanceof Subscription ? $this->resolvePlan($subscription) : null;
        $current_code = $current_plan instanceof Plan ? $current_plan->getCode() : '';

        $options = [];
        foreach ($plans as $plan) {
            if (!$plan instanceof Plan) {
                continue;
            }

            $decision = $this->canChangeToPlan($account_id, $plan);

            $options[] = [
                'plan' => $plan,
                'is_current' => $plan->getCode() === $current_code,
                'allowed' => (bool) ($decision['allowed'] ?? false),
                'is_downgrade' => (bool) ($decision['is_downgrade'] ?? false),
                'message' => (string) ($decision['message'] ?? ''),
            ];
        }

        return $options;
    }

    private function resolvePlan(Subscription $subscription): ?Plan {
        $plan_id = $subscription->getPlanId();
        if ($plan_id > 0) {
            $plan = $this->plan_repository->get_by_id($plan_id);
            if ($plan instanceof Plan) {
                return $plan;
            }
        }

        $plan_code = $subscription->getPlanCode();
        if ($plan_code === '') {
            return null;
        }

        return $this->plan_repository->getByCode($plan_code);
    }

    private function isDowngrade(Plan $current_plan, Plan $target_plan): bool {
        if ($target_plan->getPrice() < $current_plan->getPrice()) {
            return true;
        }

        $current_limit = $current_plan->getBookingLimit();
        $target_limit = $target_plan->getBookingLimit();

        if ($current_limit === null) {
            return $target_limit !== null;
        }

        return $target_limit !== null && $target_limit < $current_limit;
    }
}
