<?php
namespace MYVH\Network;

use MYVH\Container\Container;
use WP_Site;
use WP_User;
use MYVH\Portal\ClientAdminService;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if (!defined('ABSPATH')) exit;

class NetworkDashboard {

    private const CLIENT_ADMINS_PAGE = 'myvh-network-client-admins';
    private const SUBSCRIPTION_STATUS_PAGE = 'myvh-network-subscription-status';
    private const PROVISIONING_SETTINGS_PAGE = 'myvh-network-provisioning-settings';
    private const PROVISIONING_MAINTENANCE_PAGE = 'myvh-network-provisioning-maintenance';
    private const INTEGRITY_PAGE = 'myvh-network-integrity';
    private const RUN_NETWORK_INTEGRITY_ACTION = 'myvh_network_run_integrity';
    private const RUN_SINGLE_SITE_INTEGRITY_ACTION = 'myvh_network_run_site_integrity';
    private const RUN_PENDING_PROVISIONING_CRON_HOOK = 'myvh_network_run_pending_provisioning';
    private LoggerInterface $logger;

    public function __construct(
        private ?SiteProvisioningRepository $provisioning_repo = null,
        private ?AccountRepository $account_repository = null,
        private ?SubscriptionRepository $subscription_repository = null,
        private ?PlanRepository $plan_repository = null,
        private ?IntegrityRepository $integrity_repository = null,
        private ?IntegrityRunManager $integrity_run_manager = null,
        private ?SiteProvisioningService $site_provisioning_service = null,
        ?LoggerInterface $logger = null
    ) {
        global $wpdb;
        $this->logger = $logger ?? new NullLogger();

        if ($this->provisioning_repo === null) {
            $this->provisioning_repo = new SiteProvisioningRepository();
        }

        if ($this->account_repository === null) {
            $this->account_repository = new AccountRepository($wpdb);
        }

        if ($this->subscription_repository === null) {
            $this->subscription_repository = new SubscriptionRepository($wpdb);
        }

        if ($this->plan_repository === null) {
            $this->plan_repository = new PlanRepository($wpdb);
        }

        if ($this->integrity_repository === null && $wpdb instanceof \wpdb) {
            $this->integrity_repository = new IntegrityRepository();
        }

        if ($this->integrity_run_manager === null && $wpdb instanceof \wpdb && $this->integrity_repository instanceof IntegrityRepository) {
            $this->integrity_run_manager = new IntegrityRunManager($this->integrity_repository, new SiteIntegrityChecker($wpdb));
        }
    }

    public function init(): void {
        add_action('network_admin_menu', [$this, 'add_menu']);
        add_action('admin_post_' . self::RUN_NETWORK_INTEGRITY_ACTION, [$this, 'handle_run_network_integrity']);
        add_action('admin_post_' . self::RUN_SINGLE_SITE_INTEGRITY_ACTION, [$this, 'handle_run_single_site_integrity']);
        add_action(self::RUN_PENDING_PROVISIONING_CRON_HOOK, [$this, 'handle_run_pending_provisioning_event'], 10, 1);
        add_action('wp_dashboard_setup', [$this, 'register_integrity_dashboard_widget']);
        add_action('wp_network_dashboard_setup', [$this, 'register_integrity_dashboard_widget']);
    }

    public function register_integrity_dashboard_widget(): void {
        if (!is_multisite() || !$this->can_manage_network_options()) {
            return;
        }

        wp_add_dashboard_widget(
            'myvh_integrity_checks_widget',
            'Village Hall Integrity Checks',
            [$this, 'render_integrity_dashboard_widget']
        );
    }

    public function render_integrity_dashboard_widget(): void {
        if (!$this->integrity_repository instanceof IntegrityRepository || !$this->integrity_run_manager instanceof IntegrityRunManager) {
            echo '<p>Integrity services are not available.</p>';
            return;
        }

        $active_run = $this->integrity_repository->get_active_run();
        $recent_runs = $this->integrity_repository->get_recent_runs(5);
        $current_blog_id = (int) get_current_blog_id();
        $status_map = $this->integrity_repository->get_site_status_map([$current_blog_id]);
        $site_status = $status_map[$current_blog_id] ?? null;

        echo '<p>Run checks directly from your dashboard or open the full integrity page for details.</p>';

        if (is_array($active_run)) {
            echo '<div class="notice notice-info" style="margin:0 0 12px 0;"><p style="margin:8px 12px;">';
            echo 'Active run #' . esc_html((string) ((int) ($active_run['id'] ?? 0))) . ' is ' . esc_html((string) ($active_run['status'] ?? 'running')) . '. ';
            echo 'Progress: ' . esc_html((string) ((int) ($active_run['processed_sites'] ?? 0))) . '/' . esc_html((string) ((int) ($active_run['total_sites'] ?? 0))) . ' sites.';
            echo '</p></div>';
        }

        echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('myvh_network_integrity_run_all');
        echo '<input type="hidden" name="action" value="' . esc_attr(self::RUN_NETWORK_INTEGRITY_ACTION) . '">';
        echo '<button type="submit" class="button button-primary">Run All Sites</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('myvh_network_integrity_run_site');
        echo '<input type="hidden" name="action" value="' . esc_attr(self::RUN_SINGLE_SITE_INTEGRITY_ACTION) . '">';
        echo '<input type="hidden" name="blog_id" value="' . esc_attr((string) $current_blog_id) . '">';
        echo '<button type="submit" class="button">Run This Site</button>';
        echo '</form>';

        echo '<a class="button" href="' . esc_url(network_admin_url('admin.php?page=' . self::INTEGRITY_PAGE)) . '">Open Full Integrity Page</a>';
        echo '</div>';

        if (is_array($site_status)) {
            echo '<p><strong>This site</strong>: Last run ' . esc_html((string) ($site_status['last_run_at'] ?? 'Never'));
            echo ' | Status: ' . esc_html((string) ($site_status['last_status'] ?? 'not-run'));
            echo ' | Errors: ' . esc_html((string) ((int) ($site_status['error_count'] ?? 0)));
            echo ' | Warnings: ' . esc_html((string) ((int) ($site_status['warning_count'] ?? 0))) . '</p>';
        } else {
            echo '<p><strong>This site</strong>: No integrity run has been recorded yet.</p>';
        }

        echo '<h4 style="margin:12px 0 8px;">Recent Runs</h4>';

        if (empty($recent_runs)) {
            echo '<p>No runs recorded yet.</p>';
            return;
        }

        echo '<ul style="margin:0;padding-left:18px;">';
        foreach ($recent_runs as $run) {
            $run_id = (int) ($run['id'] ?? 0);
            $status = (string) ($run['status'] ?? 'unknown');
            $target_blog = (int) ($run['target_blog_id'] ?? 0);
            $target = $target_blog > 0 ? ('Site #' . $target_blog) : 'All Sites';
            $view_link = add_query_arg([
                'page' => self::INTEGRITY_PAGE,
                'run_id' => $run_id,
            ], network_admin_url('admin.php'));

            echo '<li style="margin:0 0 4px 0;">';
            echo '#' . esc_html((string) $run_id) . ' ';
            echo esc_html($status) . ' ';
            echo '(' . esc_html($target) . ') ';
            echo '<a href="' . esc_url($view_link) . '">View</a>';
            echo '</li>';
        }
        echo '</ul>';
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
            'Subscription Status',
            'Subscription Status',
            'manage_network_options',
            self::SUBSCRIPTION_STATUS_PAGE,
            [$this, 'render_subscription_status_page']
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

        add_submenu_page(
            'myvh-network',
            'Integrity Checks',
            'Integrity Checks',
            'manage_network_options',
            self::INTEGRITY_PAGE,
            [$this, 'render_integrity_page']
        );
    }

    public function render_menu_icons(): void {
        $icon_map = [
            'admin.php?page=' . self::CLIENT_ADMINS_PAGE => 'dashicons-admin-users',
            'admin.php?page=' . self::SUBSCRIPTION_STATUS_PAGE => 'dashicons-chart-bar',
            'admin.php?page=' . self::PROVISIONING_SETTINGS_PAGE => 'dashicons-admin-tools',
            'admin.php?page=' . self::PROVISIONING_MAINTENANCE_PAGE => 'dashicons-clipboard',
            'admin.php?page=' . self::INTEGRITY_PAGE => 'dashicons-shield',
        ];
        $json_map = wp_json_encode($icon_map);

        echo '<style>.myvh-submenu-icon{font-size:16px;width:18px;height:18px;line-height:18px;margin-right:6px;vertical-align:text-bottom;}</style>';
        echo '<script>(function(){var map=' . $json_map . ';function apply(){if(!map){return;}Object.keys(map).forEach(function(href){var link=document.querySelector("#adminmenu .wp-submenu a[href=\""+href+"\"]");if(!link||link.dataset.myvhIconApplied==="1"){return;}var icon=document.createElement("span");icon.className="dashicons "+map[href]+" myvh-submenu-icon";icon.setAttribute("aria-hidden","true");link.insertBefore(icon,link.firstChild);link.dataset.myvhIconApplied="1";});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",apply);}else{apply();}})();</script>';
    }

    public function render_dashboard(): void {
        echo '<div class="wrap"><h1>Village Hall Network Dashboard</h1>';

        echo '<details class="postbox" open style="max-width: 980px; margin: 16px 0;">';
        echo '<summary style="cursor:pointer; padding: 12px 16px; font-size: 16px; font-weight: 600; user-select: none;">Integrity Checks</summary>';
        echo '<div style="padding: 0 16px 12px;">';
        echo '<p style="margin-top: 0;">Quick access to run and review integrity checks without leaving this dashboard.</p>';
        $this->render_integrity_dashboard_widget();
        echo '</div>';
        echo '</details>';

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

    public function render_subscription_status_page(): void {
        if (!$this->can_manage_network_options()) {
            wp_die('Sorry, you are not allowed to access this page.');
        }

        $sites = $this->get_subscription_status_sites();

        echo '<div class="wrap">';
        echo '<h1>Subscription Status</h1>';
        echo '<p>Review the current subscription plan for each live site on the network.</p>';

        if (empty($sites)) {
            echo '<div class="notice notice-info"><p>No eligible sites were found.</p></div>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Site</th><th>Plan</th><th>Status</th><th>Invoice</th><th>Trial days left</th></tr></thead><tbody>';

        foreach ($sites as $site) {
            $blog_id = (int) $site->blog_id;
            $site_name = get_blog_option($blog_id, 'blogname', sprintf('Site %d', $blog_id));
            $site_url = $this->get_subscription_dashboard_url($blog_id);
            $subscription = $this->resolve_subscription_for_site($blog_id);

            $plan_label = 'No subscription';
            $status_label = 'No subscription';
            $invoice_label = '—';
            $trial_days_left = '—';

            if ($subscription instanceof Subscription) {
                $plan_label = $this->resolve_plan_label($subscription);
                $status_label = ucfirst(str_replace('_', ' ', $subscription->getStatus()));
                $invoice_label = $this->get_invoice_state_label($subscription);

                if ($subscription->isTrial()) {
                    $days_remaining = $this->get_trial_days_remaining($subscription);
                    $trial_days_left = $days_remaining === null
                        ? 'Unknown'
                        : ($days_remaining === 0
                            ? 'Expired'
                            : sprintf('%d days', $days_remaining));
                }
            }

            echo '<tr>';
            echo '<td><a href="' . esc_url($site_url) . '">' . esc_html($site_name) . '</a></td>';
            echo '<td>' . esc_html($plan_label) . '</td>';
            echo '<td>' . esc_html($status_label) . '</td>';
            echo '<td>' . esc_html($invoice_label) . '</td>';
            echo '<td>' . esc_html($trial_days_left) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function render_client_admins_page(): void {
        if (!$this->can_manage_network_options()) {
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
        if (!$this->can_manage_network_options()) {
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
        if (!$this->can_manage_network_options()) {
            wp_die('Sorry, you are not allowed to access this page.');
        }

        // Handle maintenance actions.
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            !empty($_POST['myvh_action'])
        ) {
            check_admin_referer('myvh_provisioning_maintenance');

            $action = sanitize_key((string) $_POST['myvh_action']);

            if ($action === 'delete' && !empty($_POST['myvh_delete_id'])) {
                $delete_id = (int) $_POST['myvh_delete_id'];
                $this->provisioning_repo->delete($delete_id);
                $redirect_url = add_query_arg([
                    'page' => self::PROVISIONING_MAINTENANCE_PAGE,
                    'myvh_notice' => 'deleted',
                ], network_admin_url('admin.php'));

                if (!headers_sent() && wp_safe_redirect($redirect_url)) {
                    exit;
                }

                $_GET['myvh_notice'] = 'deleted';
            }

            if ($action === 'run_pending_provisioning' && !empty($_POST['myvh_run_id'])) {
                $run_id = (int) $_POST['myvh_run_id'];
                $notice = 'run_failed';

                $this->logger->info('Network provisioning maintenance run requested.', [
                    'provision_id' => $run_id,
                    'action' => $action,
                ]);

                try {
                    $service = $this->resolve_site_provisioning_service();

                    if (!$service instanceof SiteProvisioningService) {
                        $this->logger->error('Network provisioning service unavailable when queueing maintenance run.', [
                            'provision_id' => $run_id,
                        ]);
                        $notice = 'run_unavailable';
                    } else {
                        $record = $this->provisioning_repo->get_by_id($run_id);
                        if (!is_array($record) || !$this->is_pending_provisioning_status((string) ($record['status'] ?? ''))) {
                            $this->logger->warning('Network provisioning maintenance run rejected because record is not pending.', [
                                'provision_id' => $run_id,
                                'record_found' => is_array($record),
                                'status' => is_array($record) ? (string) ($record['status'] ?? '') : null,
                            ]);
                            $notice = 'run_failed';
                        } else {
                            $is_already_queued = wp_next_scheduled(self::RUN_PENDING_PROVISIONING_CRON_HOOK, [$run_id]) !== false;
                            if (!$is_already_queued) {
                                wp_schedule_single_event(time() + 5, self::RUN_PENDING_PROVISIONING_CRON_HOOK, [$run_id]);
                            }

                            if (\defined('DISABLE_WP_CRON') && (bool) \constant('DISABLE_WP_CRON')) {
                                $this->logger->warning('Network provisioning maintenance run queued, but WP-Cron is disabled.', [
                                    'provision_id' => $run_id,
                                    'already_queued' => $is_already_queued,
                                ]);
                                $notice = 'run_queued_cron_disabled';
                            } else {
                                if (function_exists('spawn_cron')) {
                                    spawn_cron(time());
                                }

                                $this->logger->info('Network provisioning maintenance run queued successfully.', [
                                    'provision_id' => $run_id,
                                    'already_queued' => $is_already_queued,
                                ]);
                                $notice = 'run_queued';
                            }
                        }
                    }
                } catch (\Throwable $exception) {
                    $this->logger->error('Network provisioning maintenance queue request threw an exception.', [
                        'provision_id' => $run_id,
                        'exception' => get_class($exception),
                        'message' => $exception->getMessage(),
                    ]);
                    $notice = 'run_failed';
                }

                $redirect_url = add_query_arg([
                    'page' => self::PROVISIONING_MAINTENANCE_PAGE,
                    'myvh_notice' => $notice,
                ], network_admin_url('admin.php'));

                if (!headers_sent() && wp_safe_redirect($redirect_url)) {
                    exit;
                }

                $_GET['myvh_notice'] = $notice;
            }
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
        $notice = sanitize_key($_GET['myvh_notice'] ?? '');

        $notices = [
            'deleted' => ['success', 'Provisioning record deleted.'],
            'run_queued' => ['success', 'Provisioning run queued and will start shortly. Refresh this page to see status updates.'],
            'run_queued_cron_disabled' => ['warning', 'Provisioning run queued, but WP-Cron is disabled. Trigger WP-Cron manually to process the job.'],
            'run_failed' => ['error', 'Provisioning run failed. Review the row status and details for more information.'],
            'run_unavailable' => ['error', 'Provisioning service is unavailable in this context.'],
        ];

        echo '<div class="wrap">';
        echo '<h1>Site Provisioning Maintenance</h1>';
        echo '<p>View and manage all site provisioning requests.</p>';

        if (isset($notices[$notice])) {
            $notice_type = $notices[$notice][0];
            $notice_text = $notices[$notice][1];
            echo '<div class="notice notice-' . esc_attr($notice_type) . ' is-dismissible"><p>' . esc_html($notice_text) . '</p></div>';
        }

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
            $blog_id = (int) ($record['blog_id'] ?? 0);
            if ($blog_id > 0) {
                $site = get_site($blog_id);

                if ($site instanceof WP_Site) {
                    $site_url = $this->build_site_url($site);
                    echo '<a href="' . esc_url($site_url) . '" target="_blank">' . esc_html((string) $blog_id) . '</a>';
                } else {
                    echo esc_html((string) $blog_id) . ' (missing)';
                }
            } else {
                echo '—';
            }
            echo '</td>';
            echo '<td>' . esc_html(wp_date('Y-m-d H:i', strtotime($record['created_at']))) . '</td>';
            echo '<td>' . esc_html(wp_date('Y-m-d H:i', strtotime($record['updated_at']))) . '</td>';
            echo '<td>';

            // View details button
            echo '<a href="#" onclick="event.preventDefault(); showProvisioningDetails(' . (int) $record['id'] . ');" class="button button-small">Details</a> ';

            if ($this->is_pending_provisioning_status((string) ($record['status'] ?? ''))) {
                echo '<form method="post" style="display:inline; margin-right:4px;" onsubmit="return confirm(\'Run provisioning now for this pending request?\');">';
                wp_nonce_field('myvh_provisioning_maintenance');
                echo '<input type="hidden" name="myvh_action" value="run_pending_provisioning">';
                echo '<input type="hidden" name="myvh_run_id" value="' . esc_attr((int) $record['id']) . '">';
                submit_button('Run Provisioning', 'small', '', false);
                echo '</form>';
            }

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

    public function handle_run_network_integrity(): void {
        if (!$this->can_manage_network_options()) {
            wp_die('Sorry, you are not allowed to access this page.');
        }

        if (!$this->integrity_run_manager instanceof IntegrityRunManager) {
            wp_die('Integrity services are not available.');
        }

        check_admin_referer('myvh_network_integrity_run_all');

        $result = $this->integrity_run_manager->queue_network_run(get_current_user_id());

        $notice = is_wp_error($result) ? 'queue_failed' : 'queued';

        wp_safe_redirect(add_query_arg([
            'page' => self::INTEGRITY_PAGE,
            'myvh_notice' => $notice,
        ], network_admin_url('admin.php')));
        exit;
    }

    public function handle_run_single_site_integrity(): void {
        if (!$this->can_manage_network_options()) {
            wp_die('Sorry, you are not allowed to access this page.');
        }

        if (!$this->integrity_run_manager instanceof IntegrityRunManager) {
            wp_die('Integrity services are not available.');
        }

        check_admin_referer('myvh_network_integrity_run_site');

        $blog_id = isset($_POST['blog_id']) ? (int) $_POST['blog_id'] : 0;
        $result = $this->integrity_run_manager->run_single_site($blog_id, get_current_user_id());

        $notice = is_wp_error($result) ? 'site_run_failed' : 'site_run_completed';

        wp_safe_redirect(add_query_arg([
            'page' => self::INTEGRITY_PAGE,
            'myvh_notice' => $notice,
            'blog_id' => $blog_id,
            'run_id' => is_wp_error($result) ? 0 : (int) $result,
        ], network_admin_url('admin.php')));
        exit;
    }

    public function handle_run_pending_provisioning_event(int $run_id): void {
        $run_id = max(0, $run_id);
        if ($run_id <= 0) {
            return;
        }

        $this->logger->info('Network provisioning cron worker started.', [
            'provision_id' => $run_id,
        ]);

        $service = $this->resolve_site_provisioning_service();
        if (!$service instanceof SiteProvisioningService) {
            $this->logger->error('Network provisioning cron worker could not resolve service.', [
                'provision_id' => $run_id,
            ]);
            return;
        }

        try {
            $result = $service->run_pending_request($run_id);

            if (!empty($result['ok'])) {
                $this->logger->info('Network provisioning cron worker completed successfully.', [
                    'provision_id' => $run_id,
                ]);
            } else {
                $this->logger->error('Network provisioning cron worker completed with failure.', [
                    'provision_id' => $run_id,
                    'message' => (string) ($result['message'] ?? ''),
                ]);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Network provisioning cron worker threw an exception.', [
                'provision_id' => $run_id,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
            // Prevent cron callbacks from fatalling and blocking subsequent events.
            return;
        }
    }

    public function render_integrity_page(): void {
        if (!$this->can_manage_network_options()) {
            wp_die('Sorry, you are not allowed to access this page.');
        }

        if (!$this->integrity_repository instanceof IntegrityRepository) {
            wp_die('Integrity services are not available.');
        }

        $sites = $this->get_subscription_status_sites();
        $blog_ids = array_map(static fn($site): int => (int) ($site->blog_id ?? 0), $sites);
        $status_map = $this->integrity_repository->get_site_status_map($blog_ids);
        $recent_runs = $this->integrity_repository->get_recent_runs(25);
        $active_run = $this->integrity_repository->get_active_run();
        $notice = sanitize_key($_GET['myvh_notice'] ?? '');
        $run_id = isset($_GET['run_id']) ? (int) $_GET['run_id'] : 0;
        $selected_run = $run_id > 0 ? $this->integrity_repository->get_run($run_id) : null;
        $selected_run_findings = $run_id > 0 ? $this->integrity_repository->get_run_findings($run_id, 300) : [];

        $notices = [
            'queued' => ['success', 'Integrity check has been queued and will run in the background.'],
            'queue_failed' => ['error', 'Integrity check could not be queued. Another run may already be active.'],
            'site_run_completed' => ['success', 'Site integrity check completed.'],
            'site_run_failed' => ['error', 'Site integrity check failed.'],
        ];

        echo '<div class="wrap">';
        echo '<h1>Integrity Checks</h1>';
        echo '<p>Run database and booking integrity checks for all sites or an individual site.</p>';

        if (isset($notices[$notice])) {
            [$notice_type, $notice_text] = $notices[$notice];
            echo '<div class="notice notice-' . esc_attr($notice_type) . ' is-dismissible"><p>' . esc_html($notice_text) . '</p></div>';
        }

        if (is_array($active_run)) {
            echo '<div class="notice notice-info"><p>';
            echo 'Active run #' . esc_html((string) ((int) ($active_run['id'] ?? 0))) . ' is ' . esc_html((string) ($active_run['status'] ?? 'running')) . '. ';
            echo 'Progress: ' . esc_html((string) ((int) ($active_run['processed_sites'] ?? 0))) . '/' . esc_html((string) ((int) ($active_run['total_sites'] ?? 0))) . ' sites.';
            echo '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin: 16px 0 24px;">';
        wp_nonce_field('myvh_network_integrity_run_all');
        echo '<input type="hidden" name="action" value="' . esc_attr(self::RUN_NETWORK_INTEGRITY_ACTION) . '">';
        submit_button('Run Integrity Check For All Sites', 'primary', '', false);
        echo '</form>';

        echo '<h2>Site Status</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Site</th><th>Last run</th><th>Status</th><th>Errors</th><th>Warnings</th><th>Actions</th></tr></thead><tbody>';

        foreach ($sites as $site) {
            $blog_id = (int) ($site->blog_id ?? 0);
            $site_name = get_blog_option($blog_id, 'blogname', sprintf('Site %d', $blog_id));
            $row = $status_map[$blog_id] ?? null;
            $last_run_at = is_array($row) && !empty($row['last_run_at']) ? (string) $row['last_run_at'] : 'Never';
            $status = is_array($row) && !empty($row['last_status']) ? (string) $row['last_status'] : 'not-run';
            $errors = is_array($row) ? (int) ($row['error_count'] ?? 0) : 0;
            $warnings = is_array($row) ? (int) ($row['warning_count'] ?? 0) : 0;

            echo '<tr>';
            echo '<td>' . esc_html($site_name) . ' <span style="color:#777;">(#' . esc_html((string) $blog_id) . ')</span></td>';
            echo '<td>' . esc_html($last_run_at) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>' . esc_html((string) $errors) . '</td>';
            echo '<td>' . esc_html((string) $warnings) . '</td>';
            echo '<td>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;">';
            wp_nonce_field('myvh_network_integrity_run_site');
            echo '<input type="hidden" name="action" value="' . esc_attr(self::RUN_SINGLE_SITE_INTEGRITY_ACTION) . '">';
            echo '<input type="hidden" name="blog_id" value="' . esc_attr((string) $blog_id) . '">';
            submit_button('Run This Site', 'secondary small', '', false);
            echo '</form>';

            if (is_array($row) && !empty($row['last_run_id'])) {
                $details_link = add_query_arg([
                    'page' => self::INTEGRITY_PAGE,
                    'run_id' => (int) $row['last_run_id'],
                ], network_admin_url('admin.php'));
                echo ' <a class="button button-small" href="' . esc_url($details_link) . '">View Last Result</a>';
            }

            echo '</td>';
            echo '</tr>';
        }

        if (empty($sites)) {
            echo '<tr><td colspan="6">No eligible sites were found.</td></tr>';
        }

        echo '</tbody></table>';

        echo '<h2 style="margin-top: 24px;">Recent Runs</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Run</th><th>Status</th><th>Target</th><th>Progress</th><th>Error Sites</th><th>Warning Sites</th><th>Completed</th><th>Actions</th></tr></thead><tbody>';

        if (empty($recent_runs)) {
            echo '<tr><td colspan="8">No runs recorded yet.</td></tr>';
        } else {
            foreach ($recent_runs as $run) {
                $target_blog = (int) ($run['target_blog_id'] ?? 0);
                $target_label = $target_blog > 0 ? ('Site #' . $target_blog) : 'All Sites';
                $view_link = add_query_arg([
                    'page' => self::INTEGRITY_PAGE,
                    'run_id' => (int) ($run['id'] ?? 0),
                ], network_admin_url('admin.php'));

                echo '<tr>';
                echo '<td>#' . esc_html((string) ((int) ($run['id'] ?? 0))) . '</td>';
                echo '<td>' . esc_html((string) ($run['status'] ?? 'unknown')) . '</td>';
                echo '<td>' . esc_html($target_label) . '</td>';
                echo '<td>' . esc_html((string) ((int) ($run['processed_sites'] ?? 0))) . '/' . esc_html((string) ((int) ($run['total_sites'] ?? 0))) . '</td>';
                echo '<td>' . esc_html((string) ((int) ($run['error_sites'] ?? 0))) . '</td>';
                echo '<td>' . esc_html((string) ((int) ($run['warning_sites'] ?? 0))) . '</td>';
                echo '<td>' . esc_html((string) ($run['completed_at'] ?? '—')) . '</td>';
                echo '<td><a class="button button-small" href="' . esc_url($view_link) . '">View</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        if (is_array($selected_run)) {
            echo '<h2 style="margin-top: 24px;">Run #' . esc_html((string) ((int) ($selected_run['id'] ?? 0))) . ' Findings</h2>';
            echo '<p>' . esc_html((string) ($selected_run['summary'] ?? '')) . '</p>';

            echo '<table class="widefat striped">';
            echo '<thead><tr><th>Site</th><th>Severity</th><th>Check</th><th>Message</th><th>When</th></tr></thead><tbody>';

            if (empty($selected_run_findings)) {
                echo '<tr><td colspan="5">No findings for this run.</td></tr>';
            } else {
                foreach ($selected_run_findings as $finding) {
                    echo '<tr>';
                    echo '<td>' . esc_html((string) ((int) ($finding['blog_id'] ?? 0))) . '</td>';
                    echo '<td>' . esc_html((string) ($finding['severity'] ?? 'info')) . '</td>';
                    echo '<td>' . esc_html((string) ($finding['check_key'] ?? '')) . '</td>';
                    echo '<td>' . esc_html((string) ($finding['message'] ?? '')) . '</td>';
                    echo '<td>' . esc_html((string) ($finding['created_at'] ?? '')) . '</td>';
                    echo '</tr>';
                }
            }

            echo '</tbody></table>';
        }

        echo '</div>';
    }

    /**
     * @return WP_Site[]
     */
    private function get_subscription_status_sites(): array {
        $sites = get_sites([
            'number' => 0,
            'count' => false,
            'fields' => '',
            'orderby' => 'domain',
            'order' => 'ASC',
        ]);

        if (!is_array($sites)) {
            return [];
        }

        $template_site_id = NetworkProvisioningSettings::template_site_id();
        $main_site_id = function_exists('get_main_site_id') ? (int) get_main_site_id() : 0;

        $filtered_sites = [];

        foreach ($sites as $site) {
            $blog_id = (int) ($site->blog_id ?? 0);

            if ($blog_id <= 0) {
                continue;
            }

            if ($template_site_id > 0 && $blog_id === $template_site_id) {
                continue;
            }

            if ($main_site_id > 0 && $blog_id === $main_site_id) {
                continue;
            }

            $filtered_sites[] = $site;
        }

        return $filtered_sites;
    }

    private function resolve_subscription_for_site(int $blog_id): ?Subscription {
        if ($blog_id <= 0 || !$this->account_repository instanceof AccountRepository) {
            return null;
        }

        $account = $this->account_repository->get_by_blog_id($blog_id);
        if (!is_array($account)) {
            $account = $this->account_repository->get_by_external_reference('blog:' . $blog_id);
        }

        $account_id = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
        if ($account_id <= 0 || !$this->subscription_repository instanceof SubscriptionRepository) {
            return null;
        }

        $subscription = $this->subscription_repository->get_latest_by_account_id($account_id);

        return $subscription instanceof Subscription ? $subscription : null;
    }

    private function get_subscription_dashboard_url(int $blog_id): string {
        return get_admin_url($blog_id, 'admin.php?page=myvh-subscription-dashboard&blog_id=' . $blog_id);
    }

    private function get_invoice_state_label(Subscription $subscription): string {
        $metadata = json_decode($subscription->getMetadataRaw(), true);
        $pending_invoice_id = is_array($metadata) ? (int) ($metadata['manual_invoice_id'] ?? 0) : 0;

        if ($subscription->getStatus() === 'past_due') {
            if ($pending_invoice_id > 0) {
                return sprintf('Invoice #%d pending', $pending_invoice_id);
            }

            return 'Invoice needed';
        }

        if ($pending_invoice_id > 0) {
            return sprintf('Invoice #%d pending', $pending_invoice_id);
        }

        return 'No invoice needed';
    }

    private function resolve_plan_label(Subscription $subscription): string {
        $plan_code = $subscription->getPlanCode();

        if ($plan_code !== '' && $this->plan_repository instanceof PlanRepository) {
            $plan = $this->plan_repository->getByCode($plan_code);
            if ($plan instanceof Plan) {
                return $plan->getName();
            }

            return $plan_code;
        }

        $plan_id = $subscription->getPlanId();
        if ($plan_id > 0 && $this->plan_repository instanceof PlanRepository) {
            $plan = $this->plan_repository->get_by_id($plan_id);
            if ($plan instanceof Plan) {
                return $plan->getName();
            }
        }

        return 'Unknown';
    }

    private function get_trial_days_remaining(Subscription $subscription): ?int {
        $trial_ends_at_raw = trim($subscription->getTrialEndsAt());
        if ($trial_ends_at_raw === '') {
            return null;
        }

        $trial_ends_at = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $trial_ends_at_raw,
            new \DateTimeZone('UTC')
        );

        if (!$trial_ends_at instanceof \DateTimeImmutable) {
            return null;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($trial_ends_at <= $now) {
            return 0;
        }

        return (int) ceil(($trial_ends_at->getTimestamp() - $now->getTimestamp()) / 86400);
    }

    private function build_site_url(WP_Site $site): string {
        $scheme = is_ssl() ? 'https://' : 'http://';

        return $scheme . $site->domain . $site->path;
    }

    private function can_manage_network_options(): bool {
        if (function_exists('get_current_network') && function_exists('current_user_can_for_site')) {
            $network = \get_current_network();
            $network_site_id = is_object($network) && isset($network->site_id) ? (int) $network->site_id : 0;

            if ($network_site_id > 0) {
                return \current_user_can_for_site($network_site_id, 'manage_network_options');
            }
        }

        return current_user_can('manage_network_options');
    }

    private function resolve_site_provisioning_service(): ?SiteProvisioningService {
        if ($this->site_provisioning_service instanceof SiteProvisioningService) {
            return $this->site_provisioning_service;
        }

        global $myvh_container;
        if ($myvh_container instanceof Container) {
            try {
                $service = $myvh_container->get(SiteProvisioningService::class);
                if ($service instanceof SiteProvisioningService) {
                    $this->site_provisioning_service = $service;
                    return $this->site_provisioning_service;
                }
            } catch (\Throwable $exception) {
                $this->logger->error('Failed to resolve SiteProvisioningService from global container.', [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if (!function_exists('app')) {
            $this->logger->error('SiteProvisioningService resolution failed: global container unavailable and app() helper missing.');
            return null;
        }

        try {
            $service = app(SiteProvisioningService::class);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to resolve SiteProvisioningService from container.', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
            return null;
        }

        if ($service instanceof SiteProvisioningService) {
            $this->site_provisioning_service = $service;
            return $this->site_provisioning_service;
        }

        return null;
    }

    private function is_pending_provisioning_status(string $status): bool {
        return strtolower(trim($status)) === 'pending';
    }

    private function get_status_class(string $status): string {
        $class = 'myvh-status-' . str_replace(' ', '-', strtolower($status));
        return $class;
    }
}
