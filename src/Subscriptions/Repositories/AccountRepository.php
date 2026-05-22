<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Repositories;

use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

class AccountRepository extends WpdbCrudRepository {
    public function __construct(wpdb $wpdb) {
        parent::__construct($wpdb, $wpdb->base_prefix . 'myvh_accounts');
    }

    public function get_by_blog_id(int $blog_id): ?array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE blog_id = %d LIMIT 1",
            $blog_id
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function get_by_external_reference(string $reference): ?array {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE external_reference = %s LIMIT 1",
            $reference
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }
}
