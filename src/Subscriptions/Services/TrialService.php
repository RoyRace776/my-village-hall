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
    private const DEFAULT_TRIAL_DAYS = 31;
    private const MYSQL_DATE_FORMAT = 'Y-m-d';
    private const MYSQL_DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private SubscriptionRepository $subscription_repository,
        private SettingsRepository $settings_repository,
        ?TimeService $time_service = null
    ) {
        $this->time_service = $time_service ?? new TimeService();
    }

    private TimeService $time_service;

    public function checkAndExpireTrial(int $account_id): ?Subscription {
        if ($account_id <= 0) {
            return null;
        }

        $subscription = $this->subscription_repository->get_active_by_account_id($account_id);
        if (!$subscription instanceof Subscription) {
            return null;
        }

        if ($subscription->isExpired()) {
            return $subscription;
        }

        $trial_ends_at_raw = trim($subscription->getTrialEndsAt());
        $is_trial_status = $subscription->isTrial();
        $is_trial_plan = $subscription->getPlanCode() === 'trial' && $trial_ends_at_raw !== '';

        if (!$is_trial_status && !$is_trial_plan) {
            return $subscription;
        }

        $now = $this->time_service->utcNow();
        $trial_ends_at = $this->parseUtcDateTime($trial_ends_at_raw);

        if (!$trial_ends_at instanceof \DateTimeImmutable || $trial_ends_at >= $now) {
            if (!$is_trial_status) {
                $subscription_id = $subscription->getId();
                if ($subscription_id > 0) {
                    $this->subscription_repository->update_by_id($subscription_id, [
                        'status' => SubscriptionStatus::TRIALING,
                    ]);

                    $refreshed = $this->subscription_repository->get_by_id($subscription_id);
                    if ($refreshed instanceof Subscription) {
                        return $refreshed;
                    }
                }

                return $subscription->withStatus(SubscriptionStatus::TRIALING);
            }

            return $subscription;
        }

        $subscription_id = $subscription->getId();
        if ($subscription_id <= 0) {
            return $subscription;
        }

        if (!$this->subscription_repository->expireSubscription($subscription_id)) {
            return $subscription;
        }

        return $subscription->withStatus(SubscriptionStatus::EXPIRED);
    }

    public function createTrialSubscription(int $account_id): ?Subscription {
        if ($account_id <= 0) {
            return null;
        }

        $current_subscription = $this->checkAndExpireTrial($account_id);
        if ($current_subscription instanceof Subscription && $current_subscription->isTrialActive($this->time_service->utcNow())) {
            return $current_subscription;
        }

        if (!$this->allowMultipleTrials()) {
            $any_subscription = $this->subscription_repository->get_any_by_account_id($account_id);
            if ($any_subscription instanceof Subscription) {
                // Explicit override only via setting: allow_multiple_trials = 1.
                return $current_subscription instanceof Subscription ? $current_subscription : null;
            }
        }

        $trial_days = $this->resolveTrialDays();

        $default_plan = (string) $this->settings_repository->get_setting_value('default_plan', '');
        if ($default_plan === '') {
            return null;
        }

        [$started_at, $trial_ends_at] = $this->buildUtcDateRange($this->time_service->now(), $trial_days);

        $subscription_id = $this->subscription_repository->startTrial([
            'account_id' => $account_id,
            'plan_id' => null,
            'plan_code' => sanitize_key($default_plan),
            'status' => SubscriptionStatus::TRIALING,
            'started_at' => $started_at,
            'current_period_start' => $started_at,
            'current_period_end' => $trial_ends_at,
            'trial_ends_at' => $trial_ends_at,
        ]);

        if ($subscription_id === false) {
            return null;
        }

        $subscription = $this->subscription_repository->get_by_id((int) $subscription_id);

        return $subscription instanceof Subscription ? $subscription : null;
    }

    public function updateTrialStartDate(int $account_id, string $trial_start_date): ?Subscription {
        if ($account_id <= 0) {
            return null;
        }

        $trial_start_date = trim($trial_start_date);
        if ($trial_start_date === '') {
            return null;
        }

        $start_at_site_tz = $this->parseTrialStartDate($trial_start_date);
        if (!$start_at_site_tz instanceof \DateTimeImmutable) {
            return null;
        }

        $subscription = $this->subscription_repository->get_active_by_account_id($account_id);
        if (!$subscription instanceof Subscription) {
            $subscription = $this->subscription_repository->get_latest_by_account_id($account_id);
        }
        if (!$subscription instanceof Subscription) {
            return null;
        }

        $is_trial_like = $subscription->isTrial()
            || ($subscription->isExpired() && $subscription->getTrialEndsAt() !== '')
            || $subscription->getPlanCode() === 'trial';

        if (!$is_trial_like) {
            return null;
        }

        $subscription_id = $subscription->getId();
        if ($subscription_id <= 0) {
            return null;
        }

        $trial_days = $this->resolveTrialDays();
        [$start_mysql, $trial_ends_at] = $this->buildUtcDateRange($start_at_site_tz, $trial_days);

        $updated = $this->subscription_repository->update_by_id($subscription_id, [
            'status' => SubscriptionStatus::TRIALING,
            'started_at' => $start_mysql,
            'current_period_start' => $start_mysql,
            'current_period_end' => $trial_ends_at,
            'trial_ends_at' => $trial_ends_at,
        ]);

        if (!$updated) {
            return null;
        }

        $refreshed = $this->subscription_repository->get_by_id($subscription_id);

        return $refreshed instanceof Subscription ? $refreshed : null;
    }

    private function resolveTrialDays(): int {
        $trial_days = (int) $this->settings_repository->get_setting_value('trial_days', (string) self::DEFAULT_TRIAL_DAYS);

        return $trial_days > 0 ? $trial_days : self::DEFAULT_TRIAL_DAYS;
    }

    private function allowMultipleTrials(): bool {
        $raw = strtolower(trim((string) $this->settings_repository->get_setting_value('allow_multiple_trials', '0')));

        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    private function parseTrialStartDate(string $trial_start_date): ?\DateTimeImmutable {
        $start_date = \DateTimeImmutable::createFromFormat(
            self::MYSQL_DATE_FORMAT,
            $trial_start_date,
            wp_timezone()
        );

        if (!$start_date instanceof \DateTimeImmutable) {
            return null;
        }

        return $start_date->setTime(0, 0, 0);
    }

    private function parseUtcDateTime(string $date_time): ?\DateTimeImmutable {
        $date_time = trim($date_time);
        if ($date_time === '') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat(
            self::MYSQL_DATETIME_FORMAT,
            $date_time,
            new \DateTimeZone('UTC')
        );

        return $parsed instanceof \DateTimeImmutable ? $parsed : null;
    }

    private function calculateTrialEndDate(\DateTimeImmutable $start_at_site_tz, int $trial_days): \DateTimeImmutable {
        return $start_at_site_tz->modify('+' . $trial_days . ' days');
    }

    private function buildUtcDateRange(\DateTimeImmutable $start_at_site_tz, int $trial_days): array {
        $end_at_site_tz = $this->calculateTrialEndDate($start_at_site_tz, $trial_days);

        return [
            $this->time_service->toUtcString($start_at_site_tz),
            $this->time_service->toUtcString($end_at_site_tz),
        ];
    }
}
