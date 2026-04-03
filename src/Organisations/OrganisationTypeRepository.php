<?php
namespace MYVH\Organisations;

use MYVH\Core\Support\RepositoryBase;
use wpdb;

if (!defined('ABSPATH')) exit;

class OrganisationTypeRepository extends RepositoryBase {
    private $organisations_table;

    public function __construct(wpdb $wpdb) {
        $this->wpdb  = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_organisation_types';
        $this->organisations_table = $wpdb->prefix . 'myvh_organisations';
    }

    public function get_by_name(string $name): ?array {
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE Name = %s LIMIT 1", $name);
        return $this->wpdb->get_row($sql, 'ARRAY_A');
    }

    public function get_default(): ?array {
        $sql = "SELECT * FROM {$this->table_name} WHERE IsDefault = 1 ORDER BY Id ASC LIMIT 1";
        return $this->wpdb->get_row($sql, 'ARRAY_A');
    }

    public function has_default(): bool {
        $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE IsDefault = 1");
        return $count > 0;
    }

    public function clear_default_except(?int $type_id = null): bool {
        $sql = "UPDATE {$this->table_name} SET IsDefault = 0";
        if (!empty($type_id)) {
            $sql .= $this->wpdb->prepare(' WHERE Id != %d', $type_id);
        }
        $result = $this->wpdb->query($sql);
        return $result !== false;
    }

    public function count_organisations_using_type(int $type_id): int {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->organisations_table} WHERE OrganisationTypeId = %d",
            $type_id
        );
        return (int) $this->wpdb->get_var($sql);
    }
}
