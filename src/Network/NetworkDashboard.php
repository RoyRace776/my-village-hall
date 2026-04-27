<?php
namespace MYVH\Network;

use WP_Site;
use WP_User;
use MYVH\Portal\ClientAdminService;

if (!defined('ABSPATH')) exit;

class NetworkDashboard {

    private const CLIENT_ADMINS_PAGE = 'myvh-network-client-admins';

    public function init(): void {
        add_action('network_admin_menu', [$this, 'add_menu']);
    }

    public function add_menu(): void {
        add_menu_page(
            'Village Hall Network',
            'Village Halls',
            'manage_network_options',
            'myvh-network',
            [$this, 'render_dashboard'],
            'dashicons-building',
            30
        );

        add_submenu_page(
            'myvh-network',
            'Client Administrators',
            'Client Admins',
            'manage_network_options',
            self::CLIENT_ADMINS_PAGE,
            [$this, 'render_client_admins_page']
        );
    }

    public function render_dashboard(): void {
        echo '<div class="wrap"><h1>Village Hall Network Dashboard</h1>';

        /** @var WP_Site[] $sites */
        $sites = get_sites([
            'number' => 0,
            'count' => false,
            'fields' => '',
        ]);

        echo '<table class="widefat"><thead>
                <tr><th>Site</th><th>Bookings</th><th>Customers</th></tr>
              </thead><tbody>';

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            global $wpdb;
            $bookings  = $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}myvh_bookings`");
            $customers = $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}myvh_customers`");
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

    public function render_client_admins_page(): void {
        if (!current_user_can('manage_network_options')) {
            wp_die('Sorry, you are not allowed to access this page.');
        }

        if (!class_exists('ClientAdminService')) {
            echo '<div class="wrap"><h1>Client Administrators</h1>';
            echo '<div class="notice notice-error"><p>Client admin service is not available.</p></div>';
            echo '</div>';
            return;
        }

        $service = new ClientAdminService();
        /** @var WP_Site[] $sites */
        $sites = get_sites([
            'number' => 0,
            'count' => false,
            'fields' => '',
            'orderby' => 'domain',
            'order' => 'ASC',
        ]);

        $selected_blog_id = isset($_REQUEST['blog_id']) ? (int) $_REQUEST['blog_id'] : 0;
        if ($selected_blog_id <= 0 && !empty($sites)) {
            $selected_blog_id = (int) $sites[0]->blog_id;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['myvh_client_admin_action'])) {
            check_admin_referer('myvh_network_client_admins');

            $action = sanitize_key($_POST['myvh_client_admin_action']);
            $redirect_args = [
                'page' => self::CLIENT_ADMINS_PAGE,
                'blog_id' => $selected_blog_id,
            ];

            if ($selected_blog_id <= 0) {
                $redirect_args['myvh_notice'] = 'invalid_site';
            } elseif ($action === 'add') {
                $identifier = sanitize_text_field($_POST['user_identifier'] ?? '');

                if ($identifier === '') {
                    $redirect_args['myvh_notice'] = 'missing_user';
                } else {
                    $user = $service->find_user($identifier);

                    if ($user instanceof WP_User) {
                        $service->add_assignment($selected_blog_id, (int) $user->ID);
                        $redirect_args['myvh_notice'] = 'added';
                    } else {
                        $redirect_args['myvh_notice'] = 'user_not_found';
                    }
                }
            } elseif ($action === 'remove') {
                $user_id = (int) ($_POST['user_id'] ?? 0);

                if ($user_id > 0) {
                    $service->remove_assignment($selected_blog_id, $user_id);
                    $redirect_args['myvh_notice'] = 'removed';
                } else {
                    $redirect_args['myvh_notice'] = 'invalid_user';
                }
            }

            wp_safe_redirect(add_query_arg($redirect_args, network_admin_url('admin.php')));
            exit;
        }

        $notices = [
            'added' => ['success', 'Client administrator added.'],
            'removed' => ['success', 'Client administrator removed.'],
            'missing_user' => ['error', 'Email address or username is required.'],
            'user_not_found' => ['error', 'No WordPress user was found with that email or username.'],
            'invalid_site' => ['error', 'Please select a valid site.'],
            'invalid_user' => ['error', 'Please select a valid user.'],
        ];

        $notice_key = sanitize_key($_GET['myvh_notice'] ?? '');
        $assigned_users = $selected_blog_id > 0 ? $service->get_assigned_users_for_blog($selected_blog_id) : [];

        echo '<div class="wrap">';
        echo '<h1>Client Administrators</h1>';
        echo '<p>Assign users as client administrators for each site without visiting individual client portals.</p>';

        if (isset($notices[$notice_key])) {
            $notice_type = $notices[$notice_key][0];
            $notice_text = $notices[$notice_key][1];
            echo '<div class="notice notice-' . esc_attr($notice_type) . ' is-dismissible"><p>' . esc_html($notice_text) . '</p></div>';
        }

        echo '<form method="get" action="' . esc_url(network_admin_url('admin.php')) . '" style="margin:16px 0 24px;">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::CLIENT_ADMINS_PAGE) . '">';
        echo '<label for="myvh-network-blog-id" style="margin-right:8px;"><strong>Client site</strong></label>';
        echo '<select id="myvh-network-blog-id" name="blog_id" style="min-width:320px; margin-right:8px;">';

        foreach ($sites as $site) {
            $blog_id = (int) $site->blog_id;
            $site_name = get_blog_option($blog_id, 'blogname', sprintf('Site %d', $blog_id));
            $domain_label = is_object($site) ? ($site->domain . $site->path) : '';
            $selected = selected($selected_blog_id, $blog_id, false);
            echo '<option value="' . esc_attr($blog_id) . '" ' . $selected . '>'
                . esc_html($site_name . ' (' . $domain_label . ')')
                . '</option>';
        }

        echo '</select>';
        submit_button('Switch Site', 'secondary', '', false);
        echo '</form>';

        if ($selected_blog_id > 0) {
            $selected_site_name = get_blog_option($selected_blog_id, 'blogname', sprintf('Site %d', $selected_blog_id));
            echo '<h2>' . esc_html($selected_site_name) . '</h2>';

            echo '<form method="post" action="' . esc_url(add_query_arg([
                'page' => self::CLIENT_ADMINS_PAGE,
                'blog_id' => $selected_blog_id,
            ], network_admin_url('admin.php'))) . '" style="max-width:620px; margin-bottom:24px;">';

            wp_nonce_field('myvh_network_client_admins');

            echo '<input type="hidden" name="myvh_client_admin_action" value="add">';
            echo '<table class="form-table" role="presentation"><tbody>';
            echo '<tr>';
            echo '<th scope="row"><label for="myvh-user-identifier">Email or username</label></th>';
            echo '<td><input id="myvh-user-identifier" type="text" name="user_identifier" class="regular-text" required></td>';
            echo '</tr>';
            echo '</tbody></table>';
            submit_button('Add Client Admin');
            echo '</form>';

            echo '<h3>Assigned Client Admins</h3>';
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>Name</th><th>Email</th><th>Username</th><th style="width:120px;">Action</th></tr></thead><tbody>';

            if (empty($assigned_users)) {
                echo '<tr><td colspan="4">No explicit client admin assignments for this site.</td></tr>';
            } else {
                foreach ($assigned_users as $assigned_user) {
                    echo '<tr>';
                    echo '<td>' . esc_html($assigned_user['display_name'] ?: $assigned_user['user_login']) . '</td>';
                    echo '<td>' . esc_html($assigned_user['user_email']) . '</td>';
                    echo '<td>' . esc_html($assigned_user['user_login']) . '</td>';
                    echo '<td>';

                    echo '<form method="post" action="' . esc_url(add_query_arg([
                        'page' => self::CLIENT_ADMINS_PAGE,
                        'blog_id' => $selected_blog_id,
                    ], network_admin_url('admin.php'))) . '" onsubmit="return confirm(\'Remove this client admin assignment?\');">';

                    wp_nonce_field('myvh_network_client_admins');

                    echo '<input type="hidden" name="myvh_client_admin_action" value="remove">';
                    echo '<input type="hidden" name="user_id" value="' . esc_attr((int) $assigned_user['ID']) . '">';
                    submit_button('Remove', 'small', '', false);
                    echo '</form>';

                    echo '</td>';
                    echo '</tr>';
                }
            }

            echo '</tbody></table>';
        }

        echo '</div>';
    }
}

// class Network_Dashboard {

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

//         $stats = (new Network_Stats())->get_overview();

//         echo '<div class="wrap"><h1>MYVH Network Dashboard</h1>';

//         echo '<div class="myvh-cards">';
//         echo '<div class="card"><strong>Sites</strong><br>' . esc_html($stats['sites']) . '</div>';
//         echo '<div class="card"><strong>Active</strong><br>' . esc_html($stats['active_sites']) . '</div>';
//         echo '<div class="card"><strong>Bookings</strong><br>' . esc_html($stats['total_bookings']) . '</div>';
//         echo '</div>';

//         echo '<h2>Bookings Trend</h2>';
//         echo '<canvas id="myvhBookingsChart" height="100"></canvas>';

//         echo '<h2>Tenants</h2>';
//         $table = new Network_Sites_Table();
//         $table->prepare_items();
//         $table->display();

//         echo '</div>';
//     }

//     public function ajax_chart() {
//         check_ajax_referer('myvh_network_nonce', 'nonce');

//         $data = (new Network_Stats())->bookings_per_day(30);

//         wp_send_json_success($data);
//     }
// }