<?php
namespace MYVH\Venues;

use MYVH\Core\Support\RepositoryBase;

if (!defined('ABSPATH')) {
    exit;
}

class VenueHoursRepository extends RepositoryBase {

    public function __construct(\wpdb $wpdb) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_venue_hours';
    }

    public function get_by_venue(int $venue_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE VenueId = %d ORDER BY DayOfWeek ASC",
            $venue_id
        );

        return $this->wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function replace_for_venue(int $venue_id, array $rows): bool {
        $this->begin();

        $deleted = $this->wpdb->delete($this->table_name, ['VenueId' => $venue_id], ['%d']);
        if ($deleted === false) {
            $this->rollback();
            return false;
        }

        foreach ($rows as $row) {
            $day_of_week = \intval($row['day_of_week'] ?? -1);
            if ($day_of_week < 0 || $day_of_week > 6) {
                continue;
            }

            $is_closed = !empty($row['is_closed']) ? 1 : 0;
            $opening_time = $is_closed ? null : sanitize_text_field($row['opening_time'] ?? '');
            $closing_time = $is_closed ? null : sanitize_text_field($row['closing_time'] ?? '');

            if ($opening_time === '') {
                $opening_time = null;
            }
            if ($closing_time === '') {
                $closing_time = null;
            }

            $ok = $this->wpdb->insert(
                $this->table_name,
                [
                    'VenueId' => $venue_id,
                    'DayOfWeek' => $day_of_week,
                    'IsClosed' => $is_closed,
                    'OpeningTime' => $opening_time,
                    'ClosingTime' => $closing_time,
                ],
                ['%d', '%d', '%d', '%s', '%s']
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
