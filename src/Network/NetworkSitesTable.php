<?php
namespace MYVH\Network;

use WP_Site;
use WP_List_Table;

if (!defined('ABSPATH')) exit;

// if (!class_exists('WP_List_Table')) {
//     require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
// }

class NetworkSitesTable extends WP_List_Table {

    public function get_columns(): array {
        return [
            'site' => 'Site',
            'domain' => 'Domain',
            'bookings' => 'Bookings',
            'actions' => 'Actions'
        ];
    }

    public function prepare_items(): void {

        /** @var WP_Site[] $sites */
        $sites = get_sites([
            'number' => 0,
            'count' => false,
            'fields' => '',
        ]);
        $data = [];
        global $wpdb;

        foreach ($sites as $site) {

            $count = 0;

            switch_to_blog($site->blog_id);

            $table = $wpdb->prefix . 'myvh_bookings';
            $table_like = $wpdb->esc_like($table);

            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_like))) {
                $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
            }

            restore_current_blog();

            $data[] = [
                'site' => get_blog_option($site->blog_id, 'blogname'),
                'domain' => $site->domain,
                'bookings' => $count,
                'actions' => $this->action_links($site->blog_id)
            ];
        }

        usort($data, fn($a,$b) => $b['bookings'] <=> $a['bookings']);

        $this->items = $data;
    }

    private function action_links(int $blog_id): string {

        $url = get_admin_url($blog_id);

        return '<a class="button" href="' . esc_url($url) . '">Open Dashboard</a>';
    }

    public function column_default($item, $column_name) {
        return $item[$column_name];
    }
}