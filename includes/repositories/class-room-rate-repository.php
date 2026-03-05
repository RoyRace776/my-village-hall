<?php
if (!defined('ABSPATH')) exit;

class MYVH_Room_Rate_Repository {

    private $wpdb;
    private $table;

    public function __construct( \wpdb $wpdb ) {
        $this->wpdb  = $wpdb;
        $this->table = $wpdb->prefix . 'myvh_room_rates';
    }

    public function create($data) {
        $result = $this->wpdb->insert(
            $this->table,
            $data,
            $this->get_format($data)
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    public function update($data, $where) {
        return $this->wpdb->update(
            $this->table,
            $data,
            $where,
            $this->get_format($data),
            ['%d']
        );
    }

    public function delete($id) {
        return $this->wpdb->delete(
            $this->table,
            ['Id' => $id],
            ['%d']
        );
    }

    public function get_by_id($id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE Id = %d",
            $id
        );

        return $this->wpdb->get_row($sql, ARRAY_A);
    }

    public function get_by_room($room_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE RoomId = %d",
            $room_id
        );

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function get_active_rate($room_id, $group_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE RoomId = %d
             AND CustomerGroupId = %d
             AND IsActive = 1
             LIMIT 1",
            $room_id,
            $group_id
        );

        return $this->wpdb->get_row($sql, ARRAY_A);
    }

    private function get_format($data) {
        $formats = [];

        foreach ($data as $value) {
            if (is_int($value)) {
                $formats[] = '%d';
            } elseif (is_float($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }
}
