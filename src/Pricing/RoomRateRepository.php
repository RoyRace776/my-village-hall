<?php
namespace MYVH\Pricing;

use MYVH\Core\Support\RepositoryBase;

if (!defined('ABSPATH')) exit;

class RoomRateRepository extends RepositoryBase{

    public function __construct( \wpdb $wpdb ) {
        $this->wpdb  = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_room_rates';
    }

    public function get_by_room($room_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE RoomId = %d",
            $room_id
        );

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function get_room_ids_with_active_rates(array $room_ids = []): array {
        $room_ids = array_values(array_filter(array_map('intval', $room_ids)));

        if (!empty($room_ids)) {
            $placeholders = implode(', ', array_fill(0, count($room_ids), '%d'));
            $sql = $this->wpdb->prepare(
                "SELECT DISTINCT RoomId FROM {$this->table_name}
                 WHERE IsActive = 1
                 AND RoomId IN ({$placeholders})",
                ...$room_ids
            );
        } else {
            $sql = "SELECT DISTINCT RoomId FROM {$this->table_name} WHERE IsActive = 1";
        }

        return array_values(array_map('intval', $this->wpdb->get_col($sql) ?: []));
    }

    public function get_active_room_rate( $room_id, $org_type_id = null ): ?array {
        if ( $org_type_id ) {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE RoomId = %d
                 AND OrganisationTypeId = %d
                 AND IsActive = 1
                 ORDER BY Priority DESC
                 LIMIT 1",
                $room_id,
                $org_type_id
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE RoomId = %d
                 AND OrganisationTypeId IS NULL
                 AND IsActive = 1
                 ORDER BY Priority DESC
                 LIMIT 1",
                $room_id
            );
        }

        return $this->wpdb->get_row( $sql, ARRAY_A );
    }
}