<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Repositories;

use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

class UsageRepository extends WpdbCrudRepository {
    public function __construct(wpdb $wpdb) {
        parent::__construct($wpdb, $wpdb->base_prefix . 'myvh_usage');
    }

    public function get_month_quantity(int $account_id, string $metric_key, string $year_month): int {
        [$period_start, $period_end] = $this->build_period($year_month);

        return $this->get_quantity_for_period($account_id, $metric_key, $period_start, $period_end);
    }

    public function get_quantity_for_period(int $account_id, string $metric_key, string $period_start, string $period_end): int {
        $metric_key = sanitize_key($metric_key);

        $sql = $this->wpdb->prepare(
                        "SELECT quantity
             FROM {$this->table_name}
                         WHERE account_id = %d
                             AND metric_key = %s
                             AND metric_period_start = %s
                             AND metric_period_end = %s
             LIMIT 1",
            $account_id,
            $metric_key,
            $period_start,
            $period_end
        );

        $quantity = $this->wpdb->get_var($sql);

        return (int) ($quantity ?? 0);
    }

    public function increment_month_quantity(int $account_id, string $metric_key, string $year_month): bool {
        [$period_start, $period_end] = $this->build_period($year_month);

        return $this->increment_quantity_for_period($account_id, $metric_key, $period_start, $period_end);
    }

    public function increment_quantity_for_period(int $account_id, string $metric_key, string $period_start, string $period_end): bool {
        $metric_key = sanitize_key($metric_key);

        $existing_id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                                "SELECT id
                 FROM {$this->table_name}
                                 WHERE account_id = %d
                                     AND metric_key = %s
                                     AND metric_period_start = %s
                                     AND metric_period_end = %s
                 LIMIT 1",
                $account_id,
                $metric_key,
                $period_start,
                $period_end
            )
        );

        if ($existing_id !== null) {
            $result = $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->table_name}
                     SET quantity = quantity + 1
                     WHERE id = %d",
                    (int) $existing_id
                )
            );

            return $result !== false;
        }

        $insert = $this->wpdb->insert(
            $this->table_name,
            [
                'account_id' => $account_id,
                'metric_key' => $metric_key,
                'metric_period_start' => $period_start,
                'metric_period_end' => $period_end,
                'quantity' => 1,
            ],
            ['%d', '%s', '%s', '%s', '%d']
        );

        return $insert !== false;
    }

    public function prune_before_month(string $year_month): int {
        [$period_start] = $this->build_period($year_month);

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE metric_period_start < %s",
                $period_start
            )
        );

        return $result === false ? 0 : (int) $result;
    }

    private function build_period(string $year_month): array {
        $normalized = preg_match('/^\d{4}-\d{2}$/', $year_month) ? $year_month : gmdate('Y-m');
        $period_start = $normalized . '-01 00:00:00';
        $period_end = gmdate('Y-m-t 23:59:59', strtotime($normalized . '-01 00:00:00'));

        return [$period_start, $period_end];
    }
}
