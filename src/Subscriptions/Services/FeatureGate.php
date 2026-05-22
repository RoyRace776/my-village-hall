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

class FeatureGate {
    public function __construct(
        private SubscriptionRepository $subscription_repository,
        private PlanRepository $plan_repository,
        private TrialService $trial_service
    ) {
    }

    public function allows(int $account_id, string $feature): bool {
        if ($account_id <= 0) {
            return false;
        }

        $feature_key = sanitize_key($feature);
        if ($feature_key === '') {
            return false;
        }

        $subscription = $this->trial_service->checkAndExpireTrial($account_id);
        if (!$subscription instanceof Subscription) {
            return false;
        }

        if ($subscription->isExpired() || $subscription->isCancelled()) {
            return false;
        }

        $plan = $this->resolve_plan($subscription);
        if (!$plan instanceof Plan) {
            return false;
        }

        $features = $plan->getFeatures();
        if (!array_key_exists($feature_key, $features)) {
            return false;
        }

        return $this->feature_value_allows($features[$feature_key]);
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

    private function feature_value_allows(mixed $value): bool {
        if ($value === null) {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value > 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === 'true' || $normalized === 'yes' || $normalized === 'unlimited') {
                return true;
            }

            if ($normalized === 'false' || $normalized === 'no' || $normalized === '') {
                return false;
            }

            if (is_numeric($normalized)) {
                return (float) $normalized > 0;
            }
        }

        return false;
    }
}
