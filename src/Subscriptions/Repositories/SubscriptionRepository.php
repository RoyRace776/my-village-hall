<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Repositories;

use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Entities\SubscriptionStatus;
use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

class SubscriptionRepository extends WpdbCrudRepository {
    public function __construct(wpdb $wpdb) {
        parent::__construct($wpdb, $wpdb->base_prefix . 'myvh_subscriptions');
    }

    public function get_active_trial_by_account_id(int $account_id): ?Subscription {
        $sql = $this->wpdb->prepare(
            "SELECT *
             FROM {$this->table_name}
                         WHERE account_id = %d
                             AND status IN (%s, %s)
                         ORDER BY id DESC
             LIMIT 1",
            $account_id,
            SubscriptionStatus::TRIALING,
            'trial'
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrate_row($row) : null;
    }

    public function get_latest_by_account_id(int $account_id): ?Subscription {
        $sql = $this->wpdb->prepare(
            "SELECT *
             FROM {$this->table_name}
             WHERE account_id = %d
             ORDER BY id DESC
             LIMIT 1",
            $account_id
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrate_row($row) : null;
    }

    public function get_any_by_account_id(int $account_id): ?Subscription {
        return $this->get_latest_by_account_id($account_id);
    }

    public function get_active_by_account_id(int $account_id): ?Subscription {
                $active_statuses = SubscriptionStatus::activeLike();

        $sql = $this->wpdb->prepare(
            "SELECT *
             FROM {$this->table_name}
             WHERE account_id = %d
                             AND status IN (%s, %s, %s, %s, %s)
             ORDER BY id DESC
             LIMIT 1",
            $account_id,
                        $active_statuses[0],
                        $active_statuses[1],
                        $active_statuses[2],
                        'pending_payment',
                        'trial'
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrate_row($row) : null;
    }

    public function update_latest_plan_for_account(int $account_id, string $plan_code, ?int $plan_id = null): bool {
        $subscription = $this->get_active_by_account_id($account_id);
        if (!$subscription instanceof Subscription) {
            return false;
        }

        $subscription_id = $subscription->getId();
        if ($subscription_id <= 0) {
            return false;
        }

        $data = [
            'plan_code' => sanitize_key($plan_code),
        ];

        if ($plan_id !== null && $plan_id > 0) {
            $data['plan_id'] = $plan_id;
        }

        return $this->update_by_id($subscription_id, $data);
    }

    public function expireSubscription(int $subscription_id): bool {
        if ($subscription_id <= 0) {
            return false;
        }

        return $this->update_by_id($subscription_id, ['status' => SubscriptionStatus::EXPIRED]);
    }

    public function startTrial(array $data): int|false {
        return $this->create($data);
    }

    public function get_latest_by_stripe_customer_id(string $stripe_customer_id): ?Subscription {
        $stripe_customer_id = trim($stripe_customer_id);
        if ($stripe_customer_id === '') {
            return null;
        }

        $sql = $this->wpdb->prepare(
            "SELECT *
             FROM {$this->table_name}
             WHERE stripe_customer_id = %s
             ORDER BY id DESC
             LIMIT 1",
            $stripe_customer_id
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrate_row($row) : null;
    }

    public function get_active_by_stripe_customer_id(string $stripe_customer_id): ?Subscription {
        $stripe_customer_id = trim($stripe_customer_id);
        if ($stripe_customer_id === '') {
            return null;
        }

                $active_statuses = SubscriptionStatus::activeLike();

        $sql = $this->wpdb->prepare(
            "SELECT *
             FROM {$this->table_name}
             WHERE stripe_customer_id = %s
                             AND status IN (%s, %s, %s, %s, %s)
             ORDER BY id DESC
             LIMIT 1",
            $stripe_customer_id,
                        $active_statuses[0],
                        $active_statuses[1],
                        $active_statuses[2],
                        'pending_payment',
                        'trial'
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrate_row($row) : null;
    }

    public function update_latest_stripe_customer_id_for_account(int $account_id, string $stripe_customer_id): bool {
        $subscription = $this->get_active_by_account_id($account_id);
        if (!$subscription instanceof Subscription) {
            return false;
        }

        $subscription_id = $subscription->getId();
        if ($subscription_id <= 0) {
            return false;
        }

        return $this->update_by_id($subscription_id, [
            'stripe_customer_id' => trim($stripe_customer_id),
            'provider' => 'stripe',
        ]);
    }

    public function update_latest_stripe_subscription_id_for_customer(string $stripe_customer_id, string $stripe_subscription_id): bool {
        $subscription = $this->get_latest_by_stripe_customer_id($stripe_customer_id);
        if (!$subscription instanceof Subscription) {
            return false;
        }

        $subscription_id = $subscription->getId();
        if ($subscription_id <= 0) {
            return false;
        }

        return $this->update_by_id($subscription_id, [
            'stripe_subscription_id' => trim($stripe_subscription_id),
            'provider_subscription_id' => trim($stripe_subscription_id),
            'provider' => 'stripe',
        ]);
    }

    public function cancel_by_stripe_subscription_id(string $stripe_subscription_id): bool {
        $stripe_subscription_id = trim($stripe_subscription_id);
        if ($stripe_subscription_id === '') {
            return false;
        }

        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE stripe_subscription_id = %s ORDER BY id DESC LIMIT 1",
            $stripe_subscription_id
        );

        $subscription_id = (int) $this->wpdb->get_var($sql);
        if ($subscription_id <= 0) {
            return false;
        }

        return $this->update_by_id($subscription_id, [
            'status' => SubscriptionStatus::CANCELLED,
            'canceled_at' => current_time('mysql'),
            'provider' => 'stripe',
        ]);
    }

    public function update_status_by_stripe_subscription_id(string $stripe_subscription_id, string $status): bool {
        $subscription_id = $this->find_latest_id_by_stripe_subscription_id($stripe_subscription_id);
        if ($subscription_id <= 0) {
            return false;
        }

        $data = [
            'status' => SubscriptionStatus::normalize($status),
            'provider' => 'stripe',
        ];

        if ($data['status'] === SubscriptionStatus::CANCELLED) {
            $data['canceled_at'] = current_time('mysql');
        }

        return $this->update_by_id($subscription_id, $data);
    }

    public function update_period_by_stripe_subscription_id(string $stripe_subscription_id, ?int $period_end_unix, ?int $period_start_unix = null): bool {
        $subscription_id = $this->find_latest_id_by_stripe_subscription_id($stripe_subscription_id);
        if ($subscription_id <= 0) {
            return false;
        }

        $data = [
            'provider' => 'stripe',
        ];

        if ($period_start_unix !== null && $period_start_unix > 0) {
            $data['current_period_start'] = gmdate('Y-m-d H:i:s', $period_start_unix);
        }

        if ($period_end_unix !== null && $period_end_unix > 0) {
            $data['current_period_end'] = gmdate('Y-m-d H:i:s', $period_end_unix);
        }

        return $this->update_by_id($subscription_id, $data);
    }

    public function update_plan_code_by_stripe_subscription_id(string $stripe_subscription_id, string $plan_code): bool {
        $plan_code = sanitize_key($plan_code);
        if ($plan_code === '') {
            return false;
        }

        $subscription_id = $this->find_latest_id_by_stripe_subscription_id($stripe_subscription_id);
        if ($subscription_id <= 0) {
            return false;
        }

        return $this->update_by_id($subscription_id, ['plan_code' => $plan_code]);
    }

    public function get_latest_by_stripe_subscription_id(string $stripe_subscription_id): ?Subscription {
        $stripe_subscription_id = trim($stripe_subscription_id);
        if ($stripe_subscription_id === '') {
            return null;
        }

        $sql = $this->wpdb->prepare(
            "SELECT *
             FROM {$this->table_name}
                 WHERE stripe_subscription_id = %s
                     OR provider_subscription_id = %s
                 ORDER BY id DESC
             LIMIT 1",
            $stripe_subscription_id,
            $stripe_subscription_id
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrate_row($row) : null;
    }

    protected function hydrate_row(array $row): Subscription {
        return Subscription::fromArray($row);
    }

    private function find_latest_id_by_stripe_subscription_id(string $stripe_subscription_id): int {
        $stripe_subscription_id = trim($stripe_subscription_id);
        if ($stripe_subscription_id === '') {
            return 0;
        }

        $sql = $this->wpdb->prepare(
                "SELECT id
             FROM {$this->table_name}
                 WHERE stripe_subscription_id = %s
                     OR provider_subscription_id = %s
                 ORDER BY id DESC
             LIMIT 1",
            $stripe_subscription_id,
            $stripe_subscription_id
        );

        return (int) $this->wpdb->get_var($sql);
    }
}
