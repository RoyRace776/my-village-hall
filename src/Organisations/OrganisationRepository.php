<?php
namespace MYVH\Organisations;

use MYVH\Core\Support\RepositoryBase;
use wpdb;

if (!defined('ABSPATH')) exit;

class OrganisationRepository extends RepositoryBase {
    private $types_table;
    public function __construct(wpdb $wpdb) {
        $this->wpdb        = $wpdb;
        $this->table_name  = $wpdb->prefix . 'myvh_organisations';
        $this->types_table = $wpdb->prefix . 'myvh_organisation_types';
    }
    public function get_default(): ?array {
        $sql = "SELECT * FROM {$this->table_name} WHERE IsDefault = 1 ORDER BY Id ASC LIMIT 1";
        return $this->wpdb->get_row($sql, ARRAY_A);
    }
    public function get_all_with_type(array $args = []): array {
        $defaults = [ 'orderby' => 'o.Name', 'order' => 'ASC', 'active_only' => false ];
        $args     = wp_parse_args($args, $defaults);
        $where = $args['active_only'] ? 'WHERE o.IsActive = 1' : '';
        $order = esc_sql($args['orderby']) . ' ' . (strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC');
        $sql = "SELECT o.*, ot.Name AS OrganisationTypeName
                FROM {$this->table_name} o
                LEFT JOIN {$this->types_table} ot ON o.OrganisationTypeId = ot.Id
                {$where}
                ORDER BY {$order}";
        return $this->wpdb->get_results($sql, ARRAY_A) ?? [];
    }
    public function count_all(): int {
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }
    public function clear_default_except(?int $organisation_id = null): bool {
        $sql = "UPDATE {$this->table_name} SET IsDefault = 0";
        if ($organisation_id) {
            $sql .= $this->wpdb->prepare(' WHERE Id != %d', $organisation_id);
        }
        $result = $this->wpdb->query($sql);
        return $result !== false;
    }
    public function has_default(): bool {
        $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE IsDefault = 1");
        return $count > 0;
    }

    public function count_bookings_for_organisation(int $organisation_id): int {
        $bookings_table = $this->wpdb->prefix . 'myvh_bookings';
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$bookings_table} WHERE OrganisationId = %d",
            $organisation_id
        );

        return (int) $this->wpdb->get_var($sql);
    }
}
