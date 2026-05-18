<?php
namespace MYVH\Network;

use WP_Site;
use WP_User;
use MYVH\Portal\ClientAdminService;

if (!defined('ABSPATH')) exit;

class NetworkDashboard {

    private const CLIENT_ADMINS_PAGE = 'myvh-network-client-admins';
    private const PROVISIONING_SETTINGS_PAGE = 'myvh-network-provisioning-settings';
    private const PROVISIONING_MAINTENANCE_PAGE = 'myvh-network-provisioning-maintenance';

    public function __construct(
        private ?SiteProvisioningRepository $provisioning_repo = null
    ) {
        if ($this->provisioning_repo === null) {
            $this->provisioning_repo = new SiteProvisioningRepository();
        }
    }

    public function init(): void {
        add_action('network_admin_menu', [$this, 'add_menu']);
    }

    public function add_menu(): void {
        add_action('network_admin_head', [$this, 'render_menu_icons']);

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

        add_submenu_page(
            'myvh-network',
            'Provisioning Settings',
            'Provisioning Settings',
            'manage_network_options',
            self::PROVISIONING_SETTINGS_PAGE,
            [$this, 'render_provisioning_settings_page']
        );

        add_submenu_page(
            'myvh-network',
            'Site Provisioning Maintenance',
            'Site Provisioning',
            'manage_network_options',
            self::PROVISIONING_MAINTENANCE_PAGE,
            [$this, 'render_provisioning_maintenance_page']
        );
    }

    public function render_menu_icons(): void {
        $icon_map = [
            'admin.php?page=' . self::CLIENT_ADMINS_PAGE => 'dashicons-admin-users',
            'admin.php?page=' . self::PROVISIONING_SETTINGS_PAGE => 'dashicons-admin-tools',
            'admin.php?page=' . self::PROVISIONING_MAINTENANCE_PAGE => 'dashicons-clipboard',
        ];
        $json_map = wp_json_encode($icon_map);

        echo '<style>.myvh-submenu-icon{font-size:16px;width:18px;height:18px;line-height:18px;margin-right:6px;vertical-align:text-bottom;}</style>';
        echo '<script>(function(){var map=' . $json_map . ';function apply(){if(!map){return;}Object.keys(map).forEach(function(href){var link=document.querySelector("#adminmenu .wp-submenu a[href=\""+href+"\"]");if(!link||link.dataset.myvhIconApplied==="1"){return;}var icon=document.createElement("span");icon.className="dashicons "+map[href]+" myvh-submenu-icon";icon.setAttribute("aria-hidden","true");link.insertBefore(icon,link.firstChild);link.dataset.myvhIconApplied="1";});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",apply);}else{apply();}})();</script>';
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
            $site_url = esc_url(get_home_url($site->blog_id));
            $bookings_count = esc_html((string) \intval($bookings));
            $customers_count = esc_html((string) \intval($customers));

            echo "<tr>
                <td><a href=\"{$site_url}\" target=\"_blank\">{$site_name}</a></td>
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

        if (!class_exists(ClientAdminService::class)) {
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

    public function render_provisioning_settings_page(): void {
        if (!current_user_can('manage_network_options')) {
            wp_die('Sorry, you are not allowed to access this page.');
        }

        $saved_notice = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['myvh_save_provisioning_settings'])) {
            check_admin_referer('myvh_network_provisioning_settings');

            NetworkProvisioningSettings::save([
                'template_site_id' => $_POST['template_site_id'] ?? 0,
                'captcha_site_key' => $_POST['captcha_site_key'] ?? '',
                'captcha_secret_key' => $_POST['captcha_secret_key'] ?? '',
            ]);

            $saved_notice = true;
        }

        $settings = NetworkProvisioningSettings::get();
        $notice = sanitize_key($_GET['myvh_notice'] ?? '');

        echo '<div class="wrap">';
        echo '<h1>Provisioning Settings</h1>';
        echo '<p>Configure clone template and CAPTCHA keys for public site provisioning.</p>';

        if ($saved_notice || $notice === 'saved') {
            echo '<div class="notice notice-success is-dismissible"><p>Provisioning settings saved.</p></div>';
        }

        echo '<form method="post" action="' . esc_url(add_query_arg([
            'page' => self::PROVISIONING_SETTINGS_PAGE,
        ], network_admin_url('admin.php'))) . '">';

        wp_nonce_field('myvh_network_provisioning_settings');

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr>';
        echo '<th scope="row"><label for="myvh-template-site-id">Template site ID</label></th>';
        echo '<td>';
        echo '<input id="myvh-template-site-id" type="number" min="1" class="small-text" name="template_site_id" value="' . esc_attr((string) $settings['template_site_id']) . '">';
        echo '<p class="description">Existing site ID used as the source template for NS Cloner.</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="myvh-captcha-site-key">CAPTCHA site key</label></th>';
        echo '<td>';
        echo '<input id="myvh-captcha-site-key" type="text" class="regular-text" name="captcha_site_key" value="' . esc_attr((string) $settings['captcha_site_key']) . '">';
        echo '<p class="description">Public key used by the front-end CAPTCHA widget.</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="myvh-captcha-secret-key">CAPTCHA secret key</label></th>';
        echo '<td>';
        echo '<input id="myvh-captcha-secret-key" type="password" class="regular-text" name="captcha_secret_key" value="' . esc_attr((string) $settings['captcha_secret_key']) . '" autocomplete="off">';
        echo '<p class="description">Server-side secret used to validate CAPTCHA tokens.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</tbody></table>';

        echo '<input type="hidden" name="myvh_save_provisioning_settings" value="1">';
        submit_button('Save Settings');
        echo '</form>';
        echo '</div>';
    }

    public function render_provisioning_maintenance_page(): void {
        if (!current_user_can('manage_network_options')) {
            wp_die('Sorry, you are not allowed to access this page.');
        }

        // Handle delete action
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            !empty($_POST['myvh_action']) &&
            $_POST['myvh_action'] === 'delete' &&
            !empty($_POST['myvh_delete_id'])
        ) {
            check_admin_referer('myvh_provisioning_maintenance');
            $delete_id = (int) $_POST['myvh_delete_id'];
            $this->provisioning_repo->delete($delete_id);
            wp_redirect(add_query_arg(['page' => self::PROVISIONING_MAINTENANCE_PAGE], network_admin_url('admin.php')));
            exit;
        }

        $current_page = (int) ($_GET['paged'] ?? 1);
        if ($current_page < 1) {
            $current_page = 1;
        }

        $per_page = 25;
        $offset = ($current_page - 1) * $per_page;
        $total_records = $this->provisioning_repo->count_all();
        $total_pages = ceil($total_records / $per_page);

        $records = $this->provisioning_repo->get_all($offset, $per_page);

        echo '<div class="wrap">';
        echo '<h1>Site Provisioning Maintenance</h1>';
        echo '<p>View and manage all site provisioning requests.</p>';

        if (empty($records)) {
            echo '<p>No provisioning records found.</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Subdomain</th>';
        echo '<th>Site Name</th>';
        echo '<th>Admin Email</th>';
        echo '<th>Status</th>';
        echo '<th>Blog ID</th>';
        echo '<th>Created</th>';
        echo '<th>Updated</th>';
        echo '<th>Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($records as $record) {
            $status = esc_html($record['status']);
            $status_class = $this->get_status_class($record['status']);

            echo '<tr>';
            echo '<td><code>' . esc_html($record['subdomain']) . '</code></td>';
            echo '<td>' . esc_html($record['site_name']) . '</td>';
            echo '<td><a href="mailto:' . esc_attr($record['admin_email']) . '">' . esc_html($record['admin_email']) . '</a></td>';
            echo '<td><span class="' . esc_attr($status_class) . '">' . $status . '</span></td>';
            echo '<td>';
            if ($record['blog_id']) {
                $site_url = get_site_url($record['blog_id']);
                echo '<a href="' . esc_url($site_url) . '" target="_blank">' . esc_html((string) $record['blog_id']) . '</a>';
            } else {
                echo '—';
            }
            echo '</td>';
            echo '<td>' . esc_html(wp_date('Y-m-d H:i', strtotime($record['created_at']))) . '</td>';
            echo '<td>' . esc_html(wp_date('Y-m-d H:i', strtotime($record['updated_at']))) . '</td>';
            echo '<td>';

            // View details button
            echo '<a href="#" onclick="event.preventDefault(); showProvisioningDetails(' . (int) $record['id'] . ');" class="button button-small">Details</a> ';

            // Delete button
            echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'Delete this provisioning record? This action cannot be undone.\');">';
            wp_nonce_field('myvh_provisioning_maintenance');
            echo '<input type="hidden" name="myvh_action" value="delete">';
            echo '<input type="hidden" name="myvh_delete_id" value="' . esc_attr((int) $record['id']) . '">';
            submit_button('Delete', 'small', '', false);
            echo '</form>';

            echo '</td>';
            echo '</tr>';

            // Hidden details row
            echo '<tr id="details-' . (int) $record['id'] . '" style="display:none; background:#f5f5f5;">';
            echo '<td colspan="8" style="padding:20px;">';
            echo '<h4>Provisioning Details</h4>';
            echo '<table style="width:100%; border-collapse:collapse;">';

            $details_fields = [
                'Token' => $record['token'],
                'First Name' => $record['admin_first_name'],
                'Last Name' => $record['admin_last_name'],
                'User ID' => $record['user_id'] ?: '—',
                'Logo URL' => $record['logo_url'] ?: '—',
                'Error' => $record['error'] ?: '—',
            ];

            foreach ($details_fields as $label => $value) {
                echo '<tr style="border-bottom:1px solid #ddd;">';
                echo '<td style="padding:8px; font-weight:bold; width:150px;">' . esc_html($label) . ':</td>';
                echo '<td style="padding:8px;"><code style="word-break:break-all;">' . esc_html($value) . '</code></td>';
                echo '</tr>';
            }

            echo '</table>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        // Pagination
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo '<span class="displaying-num">' . sprintf(
                esc_html('%d-%d of %d'),
                $offset + 1,
                min($offset + $per_page, $total_records),
                $total_records
            ) . '</span> ';

            // Previous page
            if ($current_page > 1) {
                echo '<a class="prev-page" href="' . esc_url(add_query_arg([
                    'page' => self::PROVISIONING_MAINTENANCE_PAGE,
                    'paged' => $current_page - 1,
                ], network_admin_url('admin.php'))) . '"><span aria-hidden="true">‹</span></a> ';
            }

            // Page numbers
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i === $current_page) {
                    echo '<span aria-current="page" class="page-numbers current"><span class="screen-reader-text">Current Page, </span>' . (int) $i . '</span> ';
                } else {
                    echo '<a class="page-numbers" href="' . esc_url(add_query_arg([
                        'page' => self::PROVISIONING_MAINTENANCE_PAGE,
                        'paged' => $i,
                    ], network_admin_url('admin.php'))) . '">' . (int) $i . '</a> ';
                }
            }

            // Next page
            if ($current_page < $total_pages) {
                echo '<a class="next-page" href="' . esc_url(add_query_arg([
                    'page' => self::PROVISIONING_MAINTENANCE_PAGE,
                    'paged' => $current_page + 1,
                ], network_admin_url('admin.php'))) . '"><span aria-hidden="true">›</span></a>';
            }

            echo '</div></div>';
        }

        echo '</div>';

        // Inline script for details toggle
        echo '<script>
        function showProvisioningDetails(id) {
            const detailsRow = document.getElementById("details-" + id);
            if (detailsRow) {
                detailsRow.style.display = detailsRow.style.display === "none" ? "table-row" : "none";
            }
        }
        </script>';

        // Inline styles
        echo '<style>
        .myvh-status-pending { color: #f0ad4e; font-weight: bold; }
        .myvh-status-verified { color: #5cb85c; font-weight: bold; }
        .myvh-status-cloning { color: #0275d8; font-weight: bold; }
        .myvh-status-site-cloned { color: #5cb85c; font-weight: bold; }
        .myvh-status-cancelled { color: #999; font-weight: bold; text-decoration: line-through; }
        .myvh-status-failed { color: #d9534f; font-weight: bold; }
        </style>';
    }

    private function get_status_class(string $status): string {
        $class = 'myvh-status-' . str_replace(' ', '-', strtolower($status));
        return $class;
    }
}
