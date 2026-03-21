<?php
if (!defined('ABSPATH')) exit;

class MYVH_Network_Dashboard {

    public function init() {
        add_action('network_admin_menu', [$this, 'add_menu']);
    }

    public function add_menu() {
        add_menu_page(
            'Village Hall Network',
            'Village Halls',
            'manage_network_options',
            'myvh-network',
            [$this, 'render_dashboard'],
            'dashicons-building',
            30
        );
    }

    public function render_dashboard() {
        echo '<div class="wrap"><h1>Village Hall Network Dashboard</h1>';

        $sites = get_sites(['number' => 0]);

        echo '<table class="widefat"><thead>
                <tr><th>Site</th><th>Bookings</th><th>Customers</th></tr>
              </thead><tbody>';

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            global $wpdb;
            $bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}myvh_bookings");
            $customers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}myvh_customers");
            $site_name = isset($site->blogname) ? esc_html($site->blogname) : '';
            $bookings_count = esc_html((string) intval($bookings));
            $customers_count = esc_html((string) intval($customers));

            echo "<tr>
                <td>{$site_name}</td>
                <td>{$bookings_count}</td>
                <td>{$customers_count}</td>
                  </tr>";

            restore_current_blog();
        }

        echo '</tbody></table></div>';
    }
}

// class MYVH_Network_Dashboard {

//     public function init() {
//         add_action('network_admin_menu', [$this, 'register_menu']);
//         add_action('network_admin_enqueue_scripts', [$this, 'enqueue']);
//         add_action('wp_ajax_myvh_network_chart', [$this, 'ajax_chart']);
//     }

//     public function register_menu() {
//         add_menu_page(
//             'MYVH Dashboard',
//             'MYVH',
//             'manage_network',
//             'myvh-network-dashboard',
//             [$this, 'render'],
//             'dashicons-chart-area',
//             30
//         );
//     }

//     public function enqueue($hook) {
//         if ($hook !== 'toplevel_page_myvh-network-dashboard') return;

//         wp_enqueue_script(
//             'myvh-network-dashboard',
//             plugins_url('../assets/js/network-dashboard.js', __FILE__),
//             ['jquery'],
//             '1.0',
//             true
//         );

//         wp_localize_script('myvh-network-dashboard', 'myvhNetwork', [
//             'ajax' => admin_url('admin-ajax.php'),
//             'nonce' => wp_create_nonce('myvh_network_nonce')
//         ]);
//     }

//     public function render() {

//         $stats = (new MYVH_Network_Stats())->get_overview();

//         echo '<div class="wrap"><h1>MYVH Network Dashboard</h1>';

//         echo '<div class="myvh-cards">';
//         echo '<div class="card"><strong>Sites</strong><br>' . esc_html($stats['sites']) . '</div>';
//         echo '<div class="card"><strong>Active</strong><br>' . esc_html($stats['active_sites']) . '</div>';
//         echo '<div class="card"><strong>Bookings</strong><br>' . esc_html($stats['total_bookings']) . '</div>';
//         echo '</div>';

//         echo '<h2>Bookings Trend</h2>';
//         echo '<canvas id="myvhBookingsChart" height="100"></canvas>';

//         echo '<h2>Tenants</h2>';
//         $table = new MYVH_Network_Sites_Table();
//         $table->prepare_items();
//         $table->display();

//         echo '</div>';
//     }

//     public function ajax_chart() {
//         check_ajax_referer('myvh_network_nonce', 'nonce');

//         $data = (new MYVH_Network_Stats())->bookings_per_day(30);

//         wp_send_json_success($data);
//     }
// }