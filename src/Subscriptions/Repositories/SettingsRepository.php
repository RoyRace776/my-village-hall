<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Repositories;

use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsRepository extends WpdbCrudRepository {
    public function __construct(wpdb $wpdb) {
        parent::__construct($wpdb, $wpdb->base_prefix . 'myvh_settings');
    }

    public function get_setting_value(string $setting_key, mixed $default = null): mixed {
        $setting_key = sanitize_key($setting_key);
        if ($setting_key === '') {
            return $default;
        }

        $sql = $this->wpdb->prepare(
                        "SELECT setting_value
             FROM {$this->table_name}
                         WHERE setting_key = %s
             LIMIT 1",
            $setting_key
        );

        $value = $this->wpdb->get_var($sql);

        if ($value === null) {
            return $default;
        }

        return $value;
    }

    public function get_global_setting(string $setting_key, mixed $default = null): mixed {
        return $this->get_setting_value($setting_key, $default);
    }

    public function set_setting_value(string $setting_key, mixed $value, int $is_autoload = 0): bool {
        $setting_key = sanitize_key($setting_key);

        if ($setting_key === '') {
            return false;
        }

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE setting_key = %s LIMIT 1",
                $setting_key
            )
        );

        if ($existing !== null) {
            $result = $this->wpdb->update(
                $this->table_name,
                [
                    'setting_value' => is_scalar($value) || $value === null ? (string) $value : wp_json_encode($value),
                    'is_autoload' => $is_autoload ? 1 : 0,
                ],
                ['id' => (int) $existing],
                ['%s', '%d'],
                ['%d']
            );

            return $result !== false;
        }

        $insert = $this->wpdb->insert(
            $this->table_name,
            [
                'setting_key' => $setting_key,
                'setting_value' => is_scalar($value) || $value === null ? (string) $value : wp_json_encode($value),
                'is_autoload' => $is_autoload ? 1 : 0,
            ],
            ['%s', '%s', '%d']
        );

        return $insert !== false;
    }
}
