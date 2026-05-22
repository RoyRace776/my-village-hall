<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Repositories;

use MYVH\Subscriptions\Entities\Plan;
use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

class PlanRepository extends WpdbCrudRepository {
    public function __construct(wpdb $wpdb) {
        parent::__construct($wpdb, $wpdb->base_prefix . 'myvh_plans');
    }

    public function getByCode(string $code): ?Plan {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE plan_key = %s LIMIT 1",
            sanitize_key($code)
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrate_row($row) : null;
    }

    public function getAllActive(): array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE is_active = %d ORDER BY id ASC",
            1
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn(array $row): Plan => $this->hydrate_row($row), $rows);
    }

    public function getStripePriceIdMonthlyByCode(string $code): ?string {
        $plan = $this->getByCode($code);
        if (!$plan instanceof Plan) {
            return null;
        }

        $price_id = $plan->getStripePriceIdMonthly();

        return $price_id !== '' ? $price_id : null;
    }

    public function getByStripePriceId(string $price_id): ?Plan {
        $price_id = trim($price_id);
        if ($price_id === '') {
            return null;
        }

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE stripe_price_id_monthly = %s LIMIT 1",
            $price_id
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrate_row($row) : null;
    }

    protected function hydrate_row(array $row): Plan {
        return Plan::fromArray($row);
    }
}
