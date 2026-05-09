<?php
namespace MYVH\AutoInvoicing;

use MYVH\Core\Support\RepositoryBase;
use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

class SingleBookingAutoInvoiceRuleRepository extends RepositoryBase {
    public function __construct(wpdb $wpdb) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_single_booking_auto_invoice_rules';
    }

    public function get_all_rules(bool $include_inactive = true): array {
        $where = $include_inactive ? '' : 'WHERE IsActive = 1';

        $sql = "SELECT * FROM {$this->table_name} {$where} ORDER BY SortOrder ASC, Id ASC";

        return $this->wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function get_active_rules(): array {
        return $this->get_all_rules(false);
    }

    public function get_first_active_rule_id(): ?int {
        $sql = "SELECT Id FROM {$this->table_name} WHERE IsActive = 1 ORDER BY SortOrder ASC, Id ASC LIMIT 1";
        $id = $this->wpdb->get_var($sql);

        if ($id === null) {
            return null;
        }

        return intval($id);
    }

    public function get_rule_options(): array {
        $options = [];

        foreach ($this->get_active_rules() as $rule) {
            $options[intval($rule['Id'])] = (string) ($rule['Name'] ?? 'Unnamed rule');
        }

        return $options;
    }

    public function is_active_rule(int $rule_id): bool {
        if ($rule_id <= 0) {
            return false;
        }

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE Id = %d AND IsActive = 1",
            $rule_id
        );

        return intval($this->wpdb->get_var($sql)) > 0;
    }

    public function count_rules(): int {
        $sql = "SELECT COUNT(*) FROM {$this->table_name}";
        return intval($this->wpdb->get_var($sql));
    }

    public function upsert_rule(array $record): int|false {
        $id = intval($record['Id'] ?? 0);
        unset($record['Id']);

        if ($id > 0) {
            $updated = $this->update($record, ['Id' => $id]);
            return $updated ? $id : false;
        }

        return $this->create($record);
    }

    public function deactivate_rules_not_in(array $rule_ids): bool {
        $rule_ids = array_values(array_filter(array_map('intval', $rule_ids)));

        if (empty($rule_ids)) {
            return $this->wpdb->query("UPDATE {$this->table_name} SET IsActive = 0") !== false;
        }

        $placeholders = implode(',', array_fill(0, count($rule_ids), '%d'));

        $sql = $this->wpdb->prepare(
            "UPDATE {$this->table_name} SET IsActive = 0 WHERE Id NOT IN ({$placeholders})",
            ...$rule_ids
        );

        return $this->wpdb->query($sql) !== false;
    }
}
