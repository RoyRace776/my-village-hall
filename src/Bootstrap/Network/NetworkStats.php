<?php
namespace MYVH\Bootstrap\Network;
use DateTime;

if (!defined('ABSPATH')) exit;

class NetworkStats {

    public function get_overview(): array {

        $sites = get_sites();
        $total = 0;
        $active = 0;

        foreach ($sites as $site) {

            switch_to_blog($site->blog_id);

            global $wpdb;
            $table = $wpdb->prefix . 'myvh_bookings';
            $table_like = $wpdb->esc_like($table);

            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_like))) {

                $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");

                if ($count > 0) $active++;
                $total += $count;
            }

            restore_current_blog();
        }

        return [
            'sites' => count($sites),
            'active_sites' => $active,
            'total_bookings' => $total
        ];
    }

    public function bookings_per_day($days = 30): array {

        $days = max(1, (int) $days);

        $results = array_fill(0, $days, 0);
        $sites = get_sites();

        foreach ($sites as $site) {

            switch_to_blog($site->blog_id);

            global $wpdb;
            $table = $wpdb->prefix . 'myvh_bookings';
            $table_like = $wpdb->esc_like($table);

            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_like))) {

                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT StartDate AS booking_date, COUNT(*) c
                         FROM $table
                         WHERE StartDate >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
                         GROUP BY StartDate",
                        $days
                    )
                );

                foreach ($rows as $row) {
                    $index = (new DateTime($row->booking_date))
                        ->diff(new DateTime())->days;

                    if ($index < $days) {
                        $results[$days - $index - 1] += (int) $row->c;
                    }
                }
            }

            restore_current_blog();
        }

        return $results;
    }
}