<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Entities\SubscriptionStatus;
use MYVH\Subscriptions\Repositories\SettingsRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;

if (!defined('ABSPATH')) {
    exit;
}

class TrialService {
    public function __construct(
        private SubscriptionRepository $subscription_repository,
        private SettingsRepository $settings_repository
    ) {
    }

    public function checkAndExpireTrial(int $account_id): ?Subscription {
        if ($account_id <= 0) {
            return null;
        }

        $subscription = $this->subscription_repository->get_active_by_account_id($account_id);
        if (!$subscription instanceof Subscription) {
            return null;
        }

        if (!$subscription->isTrial()) {
            return $subscription;
        }

        $trial_ends_at = $subscription->getTrialEndsAt();
        if ($trial_ends_at === '') {
            return $subscription;
        }

        $trial_ends_ts = strtotime($trial_ends_at);
        $now = (int) current_time('timestamp');

        if ($trial_ends_ts === false || $trial_ends_ts >= $now) {
            return $subscription;
        }

        $subscription_id = $subscription->getId();
        if ($subscription_id <= 0) {
            return $subscription;
        }

        if (!$this->subscription_repository->update_by_id($subscription_id, ['status' => SubscriptionStatus::EXPIRED])) {
            return $subscription;
        }

        return $subscription->withStatus(SubscriptionStatus::EXPIRED);
    }

    public function createTrialSubscription(int $account_id): ?Subscription {
        if ($account_id <= 0) {
            return null;
        }

        $current_subscription = $this->checkAndExpireTrial($account_id);
        if ($current_subscription instanceof Subscription && $current_subscription->isTrial()) {
            return $current_subscription;
        }

        $trial_days = (int) $this->settings_repository->get_setting_value('trial_days', '0');
        if ($trial_days <= 0) {
            return null;
        }

        $default_plan = (string) $this->settings_repository->get_setting_value('default_plan', '');
        if ($default_plan === '') {
            return null;
        }

        $now = current_time('mysql');
        $trial_ends_at = gmdate('Y-m-d H:i:s', strtotime($now . ' +' . $trial_days . ' days'));

        $subscription_id = $this->subscription_repository->create([
            'account_id' => $account_id,
            'plan_id' => null,
            'plan_code' => sanitize_key($default_plan),
            'status' => SubscriptionStatus::TRIALING,
            'started_at' => $now,
            'current_period_start' => $now,
            'current_period_end' => $trial_ends_at,
            'trial_ends_at' => $trial_ends_at,
        ]);

        if ($subscription_id === false) {
            return null;
        }

        $subscription = $this->subscription_repository->get_by_id((int) $subscription_id);

        return $subscription instanceof Subscription ? $subscription : null;
    }
}
