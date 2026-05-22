<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Repositories\PlanRepository;

if (!defined('ABSPATH')) {
    exit;
}

class PlanService {
    public function __construct(private PlanRepository $plan_repository) {
    }

    public function getActivePlans(): array {
        return $this->plan_repository->getAllActive();
    }

    public function getByCode(string $plan_code): ?Plan {
        $plan_code = sanitize_key($plan_code);
        if ($plan_code === '') {
            return null;
        }

        return $this->plan_repository->getByCode($plan_code);
    }

    public function getById(int $plan_id): ?Plan {
        if ($plan_id <= 0) {
            return null;
        }

        $plan = $this->plan_repository->get_by_id($plan_id);

        return $plan instanceof Plan ? $plan : null;
    }

    public function getBookingLimitForPlan(?Plan $plan): ?int {
        if (!$plan instanceof Plan) {
            return null;
        }

        return $plan->getBookingLimit();
    }

    public function getFeatureValue(?Plan $plan, string $feature_key): mixed {
        if (!$plan instanceof Plan) {
            return null;
        }

        $feature_key = sanitize_key($feature_key);
        if ($feature_key === '') {
            return null;
        }

        $features = $plan->getFeatures();

        return $features[$feature_key] ?? null;
    }
}
