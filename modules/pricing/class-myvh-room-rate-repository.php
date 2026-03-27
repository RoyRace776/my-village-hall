<?php
if (!defined('ABSPATH')) exit;

class Room_Rate_Repository extends Repository_Base{

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
