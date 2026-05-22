<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Repositories;

use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

abstract class WpdbCrudRepository {
    protected wpdb $wpdb;
    protected string $table_name;
    protected string $primary_key = 'id';

    public function __construct(wpdb $wpdb, string $table_name) {
        $this->wpdb = $wpdb;
        $this->table_name = $table_name;
    }

    public function create(array $data): int|false {
        $result = $this->wpdb->insert($this->table_name, $data, $this->get_format($data));

        if ($result === false) {
            return false;
        }

        return (int) $this->wpdb->insert_id;
    }

    public function get_by_id(int $id): mixed {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE {$this->primary_key} = %d",
            $id
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrate_row($row) : null;
    }

    public function get_all(int $limit = 100, int $offset = 0): array {
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY {$this->primary_key} ASC LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn(array $row): mixed => $this->hydrate_row($row), $rows);
    }

    public function update_by_id(int $id, array $data): bool {
        if (empty($data)) {
            return false;
        }

        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            [$this->primary_key => $id],
            $this->get_format($data),
            ['%d']
        );

        return $result !== false;
    }

    public function delete_by_id(int $id): bool {
        $result = $this->wpdb->delete($this->table_name, [$this->primary_key => $id], ['%d']);

        return $result !== false;
    }

    protected function get_format(array $data): array {
        $formats = [];

        foreach ($data as $value) {
            if (is_int($value)) {
                $formats[] = '%d';
                continue;
            }

            if (is_float($value)) {
                $formats[] = '%f';
                continue;
            }

            $formats[] = '%s';
        }

        return $formats;
    }

    protected function hydrate_row(array $row): mixed {
        return $row;
    }
}
