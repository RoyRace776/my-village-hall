<?php
if (!defined('ABSPATH')) exit;

class MYVH_Network_Stats {

    public function get_overview() {

        $sites = get_sites();
        $total = 0;
        $active = 0;

        foreach ($sites as $site) {

            switch_to_blog($site->blog_id);

            global $wpdb;
            $table = $wpdb->prefix . 'myvh_bookings';

            if ($wpdb->get_var("SHOW TABLES LIKE '$table'")) {

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

    public function bookings_per_day($days = 30) {

        $results = array_fill(0, $days, 0);
        $sites = get_sites();

        foreach ($sites as $site) {

            switch_to_blog($site->blog_id);

            global $wpdb;
            $table = $wpdb->prefix . 'myvh_bookings';

            if ($wpdb->get_var("SHOW TABLES LIKE '$table'")) {

                $rows = $wpdb->get_results(
                    "SELECT booking_date, COUNT(*) c
                     FROM $table
                     WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                     GROUP BY booking_date"
                );

                foreach ($rows as $row) {
                    $index = (new DateTime($row->booking_date))
                        ->diff(new DateTime())->days;

                    if ($index < $days) {
                        $results[$days - $index - 1] += $row->c;
                    }
                }
            }

            restore_current_blog();
        }

        return $results;
    }
}