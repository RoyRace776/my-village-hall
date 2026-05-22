<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

use MYVH\Subscriptions\Entities\SubscriptionStatus;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;

if (!defined('ABSPATH')) {
    exit;
}

class SubscriptionLifecycleService {
    private const SECONDS_PER_DAY = 86400;

    public function __construct(
        private SubscriptionRepository $subscription_repository,
        private SettingsService $settings_service,
        private SubscriptionEventLogger $event_logger,
        private UsageService $usage_service
    ) {
    }

    public function runDaily(): void {
        $this->expireTrials();
        $this->expireOverdueSubscriptions();
        $this->applyScheduledPlanChanges();

        // Usage is month-bucketed; pruning keeps the table small on long-running installs.
        $this->usage_service->pruneHistoricalUsage(6);
    }

    public function expireTrials(): int {
        global $wpdb;

        $table = $wpdb->base_prefix . 'myvh_subscriptions';
        $now = current_time('mysql');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, account_id, status
                 FROM {$table}
                 WHERE status = %s
                   AND trial_ends_at IS NOT NULL
                   AND trial_ends_at < %s",
                                SubscriptionStatus::TRIALING,
                $now
            ),
            ARRAY_A
        );

        if (!is_array($rows) || empty($rows)) {
            return 0;
        }

        $updated = 0;
        foreach ($rows as $row) {
            $subscription_id = (int) ($row['id'] ?? 0);
            $account_id = (int) ($row['account_id'] ?? 0);

            if ($subscription_id <= 0 || $account_id <= 0) {
                continue;
            }

            if (!$this->subscription_repository->update_by_id($subscription_id, ['status' => SubscriptionStatus::EXPIRED])) {
                continue;
            }

            $updated++;
            $this->event_logger->log(
                $account_id,
                'trial_expired',
                SubscriptionStatus::TRIALING,
                SubscriptionStatus::EXPIRED,
                'lifecycle'
            );
        }

        return $updated;
    }

    public function expireOverdueSubscriptions(): int {
        global $wpdb;

        $table = $wpdb->base_prefix . 'myvh_subscriptions';
        $now_ts = (int) current_time('timestamp');
        $grace_days = $this->settings_service->getGracePeriodDays();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, account_id, status, current_period_end
                 FROM {$table}
                 WHERE status IN (%s, %s)",
                SubscriptionStatus::PAST_DUE,
                'pending_payment'
            ),
            ARRAY_A
        );

        if (!is_array($rows) || empty($rows)) {
            return 0;
        }

        $updated = 0;
        foreach ($rows as $row) {
            $subscription_id = (int) ($row['id'] ?? 0);
            $account_id = (int) ($row['account_id'] ?? 0);
            $previous_status = sanitize_key((string) ($row['status'] ?? ''));
            $period_end_raw = (string) ($row['current_period_end'] ?? '');
            $period_end = strtotime($period_end_raw);

            if ($subscription_id <= 0 || $account_id <= 0 || $period_end === false) {
                continue;
            }

            $cutoff = $period_end + ($grace_days * self::SECONDS_PER_DAY);
            if ($now_ts <= $cutoff) {
                continue;
            }

            if (!$this->subscription_repository->update_by_id($subscription_id, [
                'status' => SubscriptionStatus::CANCELLED,
                'canceled_at' => current_time('mysql'),
            ])) {
                continue;
            }

            $updated++;
            $this->event_logger->log(
                $account_id,
                'payment_grace_expired',
                $previous_status,
                SubscriptionStatus::CANCELLED,
                'lifecycle',
                ['current_period_end' => $period_end_raw]
            );
        }

        return $updated;
    }

    public function applyScheduledPlanChanges(): int {
        global $wpdb;

        $table = $wpdb->base_prefix . 'myvh_subscriptions';
        $now = current_time('mysql');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, account_id, status, plan_code, plan_id, metadata
                 FROM {$table}
                 WHERE status IN (%s, %s, %s)
                   AND metadata LIKE %s",
                SubscriptionStatus::TRIALING,
                SubscriptionStatus::ACTIVE,
                SubscriptionStatus::PAST_DUE,
                '%scheduled_plan_change%'
            ),
            ARRAY_A
        );

        if (!is_array($rows) || empty($rows)) {
            return 0;
        }

        $applied = 0;

        foreach ($rows as $row) {
            $subscription_id = (int) ($row['id'] ?? 0);
            $account_id = (int) ($row['account_id'] ?? 0);
            $previous_status = sanitize_key((string) ($row['status'] ?? ''));
            $previous_plan_code = sanitize_key((string) ($row['plan_code'] ?? ''));
            $previous_plan_id = (int) ($row['plan_id'] ?? 0);
            $metadata_raw = (string) ($row['metadata'] ?? '');
            $metadata = json_decode($metadata_raw, true);

            if ($subscription_id <= 0 || $account_id <= 0 || !is_array($metadata)) {
                continue;
            }

            $scheduled = is_array($metadata['scheduled_plan_change'] ?? null)
                ? $metadata['scheduled_plan_change']
                : null;

            if (!is_array($scheduled)) {
                continue;
            }

            $effective_at = (string) ($scheduled['effective_at'] ?? '');
            $effective_ts = strtotime($effective_at);
            $now_ts = (int) current_time('timestamp');
            if ($effective_ts === false || $effective_ts > $now_ts) {
                continue;
            }

            $new_plan_code = sanitize_key((string) ($scheduled['plan_code'] ?? ''));
            if ($new_plan_code === '') {
                continue;
            }

            $new_plan_id = (int) ($scheduled['plan_id'] ?? 0);

            unset($metadata['scheduled_plan_change']);

            $updated = $this->subscription_repository->update_by_id($subscription_id, [
                'plan_code' => $new_plan_code,
                'plan_id' => $new_plan_id > 0 ? $new_plan_id : null,
                'metadata' => wp_json_encode($metadata),
            ]);

            if (!$updated) {
                continue;
            }

            $applied++;
            $this->event_logger->log(
                $account_id,
                'scheduled_plan_change_applied',
                $previous_status,
                $previous_status,
                'lifecycle',
                [
                    'from_plan_code' => $previous_plan_code,
                    'to_plan_code' => $new_plan_code,
                    'from_plan_id' => $previous_plan_id,
                    'to_plan_id' => $new_plan_id,
                ]
            );
        }

        return $applied;
    }
}
