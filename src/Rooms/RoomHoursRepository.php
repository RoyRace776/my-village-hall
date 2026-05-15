<?php
namespace MYVH\Rooms;

use MYVH\Core\Support\RepositoryBase;

if (!defined('ABSPATH')) {
    exit;
}

class RoomHoursRepository extends RepositoryBase {

    public function __construct(\wpdb $wpdb) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_room_hours';
    }

    public function get_by_room(int $room_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE RoomId = %d ORDER BY DayOfWeek ASC",
            $room_id
        );

        return $this->wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function replace_for_room(int $room_id, array $rows): bool {
        $this->begin();

        $deleted = $this->wpdb->delete($this->table_name, ['RoomId' => $room_id], ['%d']);
        if ($deleted === false) {
            $this->rollback();
            return false;
        }

        foreach ($rows as $row) {
            $day_of_week = \intval($row['day_of_week'] ?? -1);
            if ($day_of_week < 0 || $day_of_week > 6) {
                continue;
            }

            $use_venue_hours = !empty($row['use_venue_hours']) ? 1 : 0;
            $is_closed = !empty($row['is_closed']) ? 1 : 0;

            if ($use_venue_hours) {
                $is_closed = 0;
            }

            $opening_time = ($use_venue_hours || $is_closed) ? null : sanitize_text_field($row['opening_time'] ?? '');
            $closing_time = ($use_venue_hours || $is_closed) ? null : sanitize_text_field($row['closing_time'] ?? '');

            if ($opening_time === '') {
                $opening_time = null;
            }
            if ($closing_time === '') {
                $closing_time = null;
            }

            $ok = $this->wpdb->insert(
                $this->table_name,
                [
                    'RoomId' => $room_id,
                    'DayOfWeek' => $day_of_week,
                    'UseVenueHours' => $use_venue_hours,
                    'IsClosed' => $is_closed,
                    'OpeningTime' => $opening_time,
                    'ClosingTime' => $closing_time,
                ],
                ['%d', '%d', '%d', '%d', '%s', '%s']
            );

            if ($ok === false) {
                $this->rollback();
                return false;
            }
        }

        $this->commit();
        return true;
    }
}
