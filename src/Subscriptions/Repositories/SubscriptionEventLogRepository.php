<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Repositories;

use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

class SubscriptionEventLogRepository extends WpdbCrudRepository {
    public function __construct(wpdb $wpdb) {
        parent::__construct($wpdb, $wpdb->base_prefix . 'myvh_subscription_event_log');
    }

    public function get_latest_for_account(int $account_id, int $limit = 50): array {
        if ($account_id <= 0) {
            return [];
        }

        $limit = max(1, $limit);

        $sql = $this->wpdb->prepare(
            "SELECT *
             FROM {$this->table_name}
             WHERE account_id = %d
             ORDER BY id DESC
             LIMIT %d",
            $account_id,
            $limit
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}
