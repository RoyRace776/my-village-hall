<?php
namespace MYVH\Addons;
use MYVH\Core\Support\RepositoryBase;

use wpdb;

class AddonRepository extends RepositoryBase {

    /**
     * Constructor
     */
    public function __construct( wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_addons';
    }

    // Custom methods preserved
    public function get_all_with_relations(): array {
        $sql = "SELECT\n                    a.*,\n                    r.Name as RoomName\n                FROM {$this->table_name} a\n                LEFT JOIN {$this->wpdb->prefix}myvh_rooms r ON a.RoomId = r.Id\n                ORDER BY a.DisplayOrder, a.Name";

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        if ($results === null) {
            error_log('MYVH Addon Repository Error (get_all_with_relations): ' . $this->wpdb->last_error);
        }
        return $results;
    }

    public function get_by_room($room_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE (RoomId = %d OR RoomId IS NULL) AND IsActive = 1 ORDER BY DisplayOrder",
            $room_id
        );
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        if ($results === null) {
            error_log('MYVH Addon Repository Error (get_by_room): ' . $this->wpdb->last_error);
        }
        return $results;
    }
}