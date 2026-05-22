<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SettingsRepository;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsService {
    public function __construct(
        private SettingsRepository $settings_repository,
        private ?PlanRepository $plan_repository = null
    ) {
    }

    public function get(string $key, mixed $default = null): mixed {
        $raw = $this->settings_repository->get_setting_value($key, null);

        if ($raw === null || $raw === '') {
            return $default;
        }

        if (!is_string($raw)) {
            return $raw;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return $raw;
    }

    public function set(string $key, mixed $value): bool {
        return $this->settings_repository->set_setting_value($key, $value);
    }

    public function getTrialDays(): int {
        return max(0, (int) $this->settings_repository->get_setting_value('trial_days', 0));
    }

    public function getGracePeriodDays(): int {
        return max(0, (int) $this->settings_repository->get_setting_value('grace_period_days', 0));
    }

    public function getDefaultPlanId(): int {
        $default_plan_id = (int) $this->settings_repository->get_setting_value('default_plan_id', 0);
        if ($default_plan_id > 0) {
            return $default_plan_id;
        }

        $default_plan_code = sanitize_key((string) $this->settings_repository->get_setting_value('default_plan', ''));
        if ($default_plan_code === '' || !$this->plan_repository instanceof PlanRepository) {
            return 0;
        }

        $plan = $this->plan_repository->getByCode($default_plan_code);

        return $plan instanceof Plan ? $plan->getId() : 0;
    }
}
