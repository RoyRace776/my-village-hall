<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Admin;

use MYVH\Bootstrap\Installer;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Entities\SubscriptionStatus;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\AccountService;
use MYVH\Subscriptions\Services\BillingNotificationService;
use MYVH\Subscriptions\Services\BillingService;
use MYVH\Subscriptions\Services\ManualInvoiceService;
use MYVH\Subscriptions\Services\PlanChangePolicyService;
use MYVH\Subscriptions\Services\SettingsService;
use MYVH\Subscriptions\Services\UsageService;

if (!defined('ABSPATH')) {
    exit;
}

class SubscriptionsAdmin {
    private const DASHBOARD_SLUG = 'myvh-subscription-dashboard';
    private const PLANS_SLUG = 'myvh-subscription-plans';
    private const BILLING_SLUG = 'myvh-subscription-billing-settings';
    private const UPGRADE_SLUG = 'myvh-subscription-upgrade';
    private const SAVE_ACTION = 'myvh_save_subscription_billing_settings';
    private const SAVE_PLANS_ACTION = 'myvh_save_subscription_plans';
    private const UPDATE_STATUS_ACTION = 'myvh_subscription_update_status';
    private const CHANGE_PLAN_ACTION = 'myvh_subscription_change_plan';
    private const GENERATE_MANUAL_INVOICE_ACTION = 'myvh_subscription_generate_manual_invoice';
    private const MARK_MANUAL_INVOICE_PAID_ACTION = 'myvh_subscription_mark_manual_invoice_paid';
    private const RUN_SCHEMA_CLEANUP_ACTION = 'myvh_subscription_run_schema_cleanup';
    private const NOTICE_TRIAL_WINDOW_DAYS = 7;

    public function __construct(
        private AccountService $account_service,
        private SubscriptionRepository $subscription_repository,
        private SettingsService $settings_service,
        private UsageService $usage_service,
        private PlanRepository $plan_repository,
        private BillingService $billing_service,
        private PlanChangePolicyService $plan_change_policy_service,
        private ManualInvoiceService $manual_invoice_service,
        private BillingNotificationService $billing_notification_service,
        private \MYVH\Subscriptions\Services\TrialService $trial_service
    ) {
    }

    public function init(): void {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'save_billing_settings']);
        add_action('admin_post_' . self::SAVE_PLANS_ACTION, [$this, 'save_plan_management']);
        add_action('admin_post_' . self::UPDATE_STATUS_ACTION, [$this, 'update_subscription_status']);
        add_action('admin_post_' . self::CHANGE_PLAN_ACTION, [$this, 'change_plan']);
        add_action('admin_post_' . self::GENERATE_MANUAL_INVOICE_ACTION, [$this, 'generate_manual_invoice']);
        add_action('admin_post_' . self::MARK_MANUAL_INVOICE_PAID_ACTION, [$this, 'mark_manual_invoice_paid']);
        add_action('admin_post_' . self::RUN_SCHEMA_CLEANUP_ACTION, [$this, 'run_schema_cleanup']);
        add_action('admin_notices', [$this, 'render_subscription_notices']);
    }

    public function register_menu(): void {
        add_submenu_page(
            'my-village-hall',
            __('Subscription Dashboard', 'my-village-hall'),
            __('Subscription Dashboard', 'my-village-hall'),
            'manage_options',
            self::DASHBOARD_SLUG,
            [$this, 'render_dashboard_page']
        );

        add_submenu_page(
            'my-village-hall',
            __('Upgrade Plan', 'my-village-hall'),
            __('Upgrade Plan', 'my-village-hall'),
            'manage_options',
            self::UPGRADE_SLUG,
            [$this, 'render_upgrade_page']
        );

        add_submenu_page(
            'my-village-hall',
            __('Manage Plans', 'my-village-hall'),
            __('Manage Plans', 'my-village-hall'),
            'manage_options',
            self::PLANS_SLUG,
            [$this, 'render_plan_management_page']
        );

        if ($this->current_user_can_manage_billing_settings()) {
            add_submenu_page(
                'my-village-hall',
                __('Billing Settings', 'my-village-hall'),
                __('Billing Settings', 'my-village-hall'),
                'manage_options',
                self::BILLING_SLUG,
                [$this, 'render_billing_settings_page']
            );
        }
    }

    public function render_dashboard_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        $target_blog_id = $this->resolve_target_blog_id();
        $context = $this->get_account_context($target_blog_id, $target_blog_id <= 0);
        $dashboard_title = __('Subscription Dashboard', 'my-village-hall');

        if (($context['blog_id'] ?? 0) > 0) {
            $site_name = get_blog_option((int) $context['blog_id'], 'blogname', sprintf('Site %d', (int) $context['blog_id']));
            $dashboard_title .= ' - ' . $site_name;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html($dashboard_title) . '</h1>';

        if (!empty($_GET['manual_invoice_generated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__('Manual subscription invoice generated. Subscription is now pending payment.', 'my-village-hall')
                . '</p></div>';
        }

        if (!empty($_GET['manual_invoice_paid'])) {
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__('Subscription payment marked as paid and subscription is now active.', 'my-village-hall')
                . '</p></div>';
        }

        if (!empty($_GET['manual_invoice_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>'
                . esc_html(wp_unslash((string) $_GET['manual_invoice_error']))
                . '</p></div>';
        }

        if ($context['account_id'] <= 0) {
            echo '<div class="notice notice-warning"><p>'
                . esc_html__('No account is linked to this site yet.', 'my-village-hall')
                . '</p></div>';
            echo '</div>';
            return;
        }

        if (!$context['subscription'] instanceof Subscription) {
            echo '<div class="notice notice-warning"><p>'
                . esc_html__('No subscription found for this account.', 'my-village-hall')
                . '</p></div>';
            echo '</div>';
            return;
        }

        $subscription = $context['subscription'];
        $plan_label = $this->resolve_plan_label($subscription);
        $status = $subscription->getStatus();
        $trial_end = $this->format_date($subscription->getTrialEndsAt());
        $usage = $this->usage_service->getCurrentUsage($context['account_id']);

        echo '<table class="widefat striped" style="max-width:900px">';
        echo '<tbody>';
        echo '<tr><th style="width:240px">' . esc_html__('Current plan', 'my-village-hall') . '</th><td>' . esc_html($plan_label) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Status', 'my-village-hall') . '</th><td>' . esc_html(ucfirst($status)) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Trial end date', 'my-village-hall') . '</th><td>' . esc_html($trial_end) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Usage this month', 'my-village-hall') . '</th><td>' . esc_html((string) $usage) . '</td></tr>';
        echo '</tbody>';
        echo '</table>';

        // ── Trial countdown badge ──────────────────────────────────────────────
        $trial_ends_at = $subscription->getTrialEndsAt();
        if ($trial_ends_at !== null && $status === SubscriptionStatus::TRIALING) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $trial_dt = $trial_ends_at instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromInterface($trial_ends_at)
                : new \DateTimeImmutable($trial_ends_at);
            $days_remaining = (int) $now->diff($trial_dt)->days;
            if ($trial_dt < $now) {
                $days_remaining = 0;
            }

            if ($days_remaining > 7) {
                $badge_color = '#1e7e34'; // green
            } elseif ($days_remaining > 2) {
                $badge_color = '#856404'; // amber
            } else {
                $badge_color = '#a72828'; // red
            }

            echo '<div style="margin-top:16px;padding:12px 16px;background:#fff;border:1px solid #ccd0d4;max-width:900px;border-radius:4px;">';
            echo '<span style="display:inline-block;padding:4px 12px;border-radius:12px;background:' . esc_attr($badge_color) . ';color:#fff;font-weight:600;font-size:13px;">';
            if ($days_remaining === 0) {
                echo esc_html__('Trial expired', 'my-village-hall');
            } elseif ($days_remaining === 1) {
                echo esc_html__('1 day remaining in trial', 'my-village-hall');
            } else {
                printf(
                    esc_html__('%d days remaining in trial', 'my-village-hall'),
                    $days_remaining
                );
            }
            echo '</span>';
            echo '</div>';
        }

        // ── Usage progress meter ───────────────────────────────────────────────
        $plan_entity = $this->plan_repository->getByCode($subscription->getPlanCode());
        $booking_limit = ($plan_entity instanceof Plan) ? (int) $plan_entity->getBookingLimit() : 0;
        if ($booking_limit > 0) {
            $percent = min(100, (int) round($usage / $booking_limit * 100));
            $meter_color = $percent >= 90 ? '#a72828' : ($percent >= 75 ? '#856404' : '#0073aa');

            echo '<div style="margin-top:16px;max-width:900px;">';
            echo '<p style="margin-bottom:4px;font-size:13px;">';
            printf(
                esc_html__('Booking usage: %1$d / %2$d this period', 'my-village-hall'),
                (int) $usage,
                (int) $booking_limit
            );
            echo '</p>';
            echo '<progress value="' . esc_attr((string) $usage) . '" max="' . esc_attr((string) $booking_limit) . '" style="width:100%;height:18px;accent-color:' . esc_attr($meter_color) . '"></progress>';
            echo '</div>';
        }

        // ── Upgrade CTA ───────────────────────────────────────────────────────
        $show_upgrade_cta = in_array($status, [
            SubscriptionStatus::TRIALING,
            SubscriptionStatus::EXPIRED,
            SubscriptionStatus::CANCELLED,
        ], true) || ($booking_limit > 0 && $usage >= $booking_limit);

        if ($show_upgrade_cta) {
            $billing_url = admin_url('admin.php?page=' . self::UPGRADE_SLUG);
            echo '<div style="margin-top:20px;">';
            echo '<a href="' . esc_url($billing_url) . '" class="button button-primary" style="font-size:14px;padding:6px 20px;">'
                . esc_html__('Upgrade Plan', 'my-village-hall')
                . '</a>';
            echo '</div>';
        }

        $this->render_subscription_status_editor($context, $subscription);
        $this->render_manual_invoice_actions($context, $subscription);

        echo '</div>';
    }

    public function render_billing_settings_page(): void {
        if (!$this->current_user_can_manage_billing_settings()) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        $trial_days = (string) $this->settings_service->get('trial_days', '31');
        $grace_period_days = (string) $this->settings_service->get('grace_period_days', '3');
        $default_plan = (string) $this->settings_service->get('default_plan', 'trial');
        $plans = $this->plan_repository->getAllActive();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Billing Settings', 'my-village-hall') . '</h1>';

        if (!empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__('Billing settings saved.', 'my-village-hall')
                . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::SAVE_ACTION) . '">';
        wp_nonce_field('myvh_subscription_billing_settings');

        echo '<table class="form-table">';

        echo '<tr>';
        echo '<th scope="row"><label for="myvh-trial-days">' . esc_html__('Trial Days', 'my-village-hall') . '</label></th>';
        echo '<td><input id="myvh-trial-days" type="number" min="1" name="trial_days" value="' . esc_attr($trial_days) . '" class="regular-text"></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="myvh-grace-period-days">' . esc_html__('Grace Period Days', 'my-village-hall') . '</label></th>';
        echo '<td><input id="myvh-grace-period-days" type="number" min="0" name="grace_period_days" value="' . esc_attr($grace_period_days) . '" class="regular-text"></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="myvh-default-plan">' . esc_html__('Default Plan', 'my-village-hall') . '</label></th>';
        echo '<td>';
        echo '<select id="myvh-default-plan" name="default_plan" class="regular-text">';

        foreach ($plans as $plan) {
            if (!$plan instanceof Plan) {
                continue;
            }

            $plan_key = $plan->getCode();
            if ($plan_key === '') {
                continue;
            }

            $plan_name = $plan->getName();
            echo '<option value="' . esc_attr($plan_key) . '" ' . selected($default_plan, $plan_key, false) . '>'
                . esc_html($plan_name)
                . '</option>';
        }

        echo '</select>';
        echo '</td>';
        echo '</tr>';

        echo '</table>';

        submit_button(__('Save Billing Settings', 'my-village-hall'));
        echo '</form>';

        echo '</div>';
    }

    public function render_plan_management_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        $plans = $this->plan_repository->get_all(1000, 0);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Manage Plans', 'my-village-hall') . '</h1>';
        echo '<p>' . esc_html__('Update the price and monthly booking allowance for each plan.', 'my-village-hall') . '</p>';

        if (!empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__('Plans updated.', 'my-village-hall')
                . '</p></div>';
        }

        if ($plans === []) {
            echo '<div class="notice notice-warning"><p>'
                . esc_html__('No plans were found.', 'my-village-hall')
                . '</p></div>';
            echo '</div>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::SAVE_PLANS_ACTION) . '">';
        wp_nonce_field('myvh_subscription_plans');

        echo '<table class="widefat striped" style="max-width:1100px;">';
        echo '<thead><tr><th>Plan</th><th>Code</th><th>Price (£)</th><th>Booking limit</th></tr></thead><tbody>';

        foreach ($plans as $plan) {
            if (!$plan instanceof Plan) {
                continue;
            }

            $plan_id = $plan->getId();
            if ($plan_id <= 0) {
                continue;
            }

            $price_value = number_format($plan->getPrice(), 2, '.', '');
            $booking_limit_value = $plan->getBookingLimit();
            $booking_limit_input = $booking_limit_value === null ? '' : (string) $booking_limit_value;

            echo '<tr>';
            echo '<td><strong>' . esc_html($plan->getName()) . '</strong></td>';
            echo '<td><code>' . esc_html($plan->getCode()) . '</code></td>';
            echo '<td><input type="number" min="0" step="0.01" name="plans[' . esc_attr((string) $plan_id) . '][price]" value="' . esc_attr($price_value) . '" class="small-text"></td>';
            echo '<td><input type="number" min="0" step="1" name="plans[' . esc_attr((string) $plan_id) . '][booking_limit]" value="' . esc_attr($booking_limit_input) . '" class="small-text"> <span class="description">Leave blank for unlimited</span></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        submit_button(__('Save Plans', 'my-village-hall'));
        echo '</form>';
        echo '</div>';
    }

    public function save_plan_management(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_subscription_plans');

        $plans = $_POST['plans'] ?? [];
        if (!is_array($plans)) {
            $plans = [];
        }

        foreach ($plans as $plan_id => $values) {
            $plan_id = (int) $plan_id;
            if ($plan_id <= 0 || !is_array($values)) {
                continue;
            }

            $updates = [];

            $price_raw = trim((string) ($values['price'] ?? ''));
            if ($price_raw !== '' && is_numeric($price_raw)) {
                $updates['price'] = (float) $price_raw;
            }

            $booking_limit_raw = trim((string) ($values['booking_limit'] ?? ''));
            if ($booking_limit_raw === '') {
                $updates['booking_limit'] = null;
            } elseif (is_numeric($booking_limit_raw)) {
                $updates['booking_limit'] = max(0, (int) $booking_limit_raw);
            }

            if ($updates !== []) {
                $this->plan_repository->update_by_id($plan_id, $updates);
            }
        }

        wp_safe_redirect(add_query_arg([
            'page' => self::PLANS_SLUG,
            'updated' => 1,
        ], admin_url('admin.php')));
        return;
    }

    public function update_subscription_status(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_subscription_update_status');

        $target_blog_id = $this->resolve_target_blog_id();
        $context = $this->get_account_context($target_blog_id, false);

        if ($context['account_id'] <= 0 || !$context['subscription'] instanceof Subscription) {
            $this->redirect_dashboard_with_error(__('No subscription found for this site.', 'my-village-hall'), $target_blog_id);
            return;
        }

        $status = SubscriptionStatus::normalize((string) ($_POST['subscription_status'] ?? ''));
        if (!SubscriptionStatus::isValid($status)) {
            $this->redirect_dashboard_with_error(__('Invalid subscription status.', 'my-village-hall'), $target_blog_id);
            return;
        }

        $update_data = ['status' => $status];
        if ($status === SubscriptionStatus::CANCELLED) {
            $update_data['canceled_at'] = current_time('mysql');
        }

        $updated = $this->subscription_repository->update_by_id($context['subscription']->getId(), $update_data);
        if (!$updated) {
            $this->redirect_dashboard_with_error(__('Failed to update subscription status.', 'my-village-hall'), $target_blog_id);
            return;
        }

        wp_safe_redirect(add_query_arg([
            'page' => self::DASHBOARD_SLUG,
            'blog_id' => $target_blog_id > 0 ? $target_blog_id : null,
            'status_updated' => 1,
        ], admin_url('admin.php')));
        return;
    }

    public function run_schema_cleanup(): void {
        if (!$this->current_user_can_manage_billing_settings()) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_subscription_schema_cleanup');

        // Run canonical installer migrations first (includes schema normalization).
        Installer::maybe_upgrade();

        $diagnostics_after_upgrade = $this->get_schema_diagnostics();
        $ok = !$diagnostics_after_upgrade['has_mixed_columns'];

        if (!$ok) {
            // Fallback to admin-level cleanup routine if mixed columns remain.
            $ok = $this->run_subscription_schema_cleanup_now();
        }

        wp_safe_redirect(add_query_arg(
            [
                'page' => self::BILLING_SLUG,
                'schema_cleanup' => $ok ? 'success' : 'failed',
            ],
            admin_url('admin.php')
        ));
        return;
    }

    private function run_subscription_schema_cleanup_now(): bool {
        global $wpdb;

        if (!$wpdb instanceof \wpdb) {
            return false;
        }

        $table_pairs = [
            'myvh_plans' => [
                'PlanKey' => 'plan_key VARCHAR(100) NOT NULL',
                'Name' => 'name VARCHAR(150) NOT NULL',
                'Description' => 'description TEXT NULL',
                'BillingInterval' => 'billing_interval VARCHAR(20) NOT NULL DEFAULT \'monthly\'',
                'Price' => 'price DECIMAL(10,2) NOT NULL DEFAULT 0.00',
                'CurrencyCode' => 'currency_code CHAR(3) NOT NULL DEFAULT \'GBP\'',
                'StripePriceIdMonthly' => 'stripe_price_id_monthly VARCHAR(191) NULL',
                'IsActive' => 'is_active TINYINT(1) NOT NULL DEFAULT 1',
                'Metadata' => 'metadata LONGTEXT NULL',
                'CreatedAt' => 'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'UpdatedAt' => 'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ],
            'myvh_accounts' => [
                'BlogId' => 'blog_id BIGINT UNSIGNED NULL',
                'OwnerUserId' => 'owner_user_id BIGINT UNSIGNED NULL',
                'PlanId' => 'plan_id BIGINT UNSIGNED NULL',
                'AccountName' => 'account_name VARCHAR(150) NOT NULL',
                'ContactEmail' => 'contact_email VARCHAR(191) NOT NULL DEFAULT \'\'',
                'Status' => 'status VARCHAR(30) NOT NULL DEFAULT \'active\'',
                'ExternalReference' => 'external_reference VARCHAR(191) NULL',
                'Metadata' => 'metadata LONGTEXT NULL',
                'CreatedAt' => 'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'UpdatedAt' => 'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ],
            'myvh_subscriptions' => [
                'AccountId' => 'account_id BIGINT UNSIGNED NOT NULL',
                'PlanId' => 'plan_id BIGINT UNSIGNED NULL',
                'PlanCode' => 'plan_code VARCHAR(100) NOT NULL',
                'Status' => 'status VARCHAR(30) NOT NULL DEFAULT \'active\'',
                'Provider' => 'provider VARCHAR(50) NULL',
                'StripeCustomerId' => 'stripe_customer_id VARCHAR(191) NULL',
                'StripeSubscriptionId' => 'stripe_subscription_id VARCHAR(191) NULL',
                'ProviderSubscriptionId' => 'provider_subscription_id VARCHAR(191) NULL',
                'StartedAt' => 'started_at DATETIME NULL',
                'CurrentPeriodStart' => 'current_period_start DATETIME NULL',
                'CurrentPeriodEnd' => 'current_period_end DATETIME NULL',
                'TrialEndsAt' => 'trial_ends_at DATETIME NULL',
                'CancelAt' => 'cancel_at DATETIME NULL',
                'CanceledAt' => 'canceled_at DATETIME NULL',
                'Metadata' => 'metadata LONGTEXT NULL',
                'CreatedAt' => 'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'UpdatedAt' => 'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ],
            'myvh_settings' => [
                'SettingKey' => 'setting_key VARCHAR(191) NOT NULL',
                'SettingValue' => 'setting_value LONGTEXT NULL',
                'IsAutoload' => 'is_autoload TINYINT(1) NOT NULL DEFAULT 0',
                'UpdatedAt' => 'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ],
            'myvh_usage' => [
                'AccountId' => 'account_id BIGINT UNSIGNED NOT NULL',
                'MetricKey' => 'metric_key VARCHAR(100) NOT NULL',
                'MetricPeriodStart' => 'metric_period_start DATETIME NOT NULL',
                'MetricPeriodEnd' => 'metric_period_end DATETIME NOT NULL',
                'Quantity' => 'quantity BIGINT NOT NULL DEFAULT 0',
                'Metadata' => 'metadata LONGTEXT NULL',
                'RecordedAt' => 'recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            ],
        ];

        foreach ($table_pairs as $table_suffix => $columns) {
            $table = $wpdb->base_prefix . $table_suffix;

            $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($table_exists === null) {
                continue;
            }

            foreach ($columns as $legacy_name => $definition) {
                [$new_name] = explode(' ', $definition, 2);

                $has_legacy = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $legacy_name)) !== null;
                $has_new = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $new_name)) !== null;

                if (!$has_legacy) {
                    continue;
                }

                if (!$has_new) {
                    if ($wpdb->query("ALTER TABLE {$table} CHANGE COLUMN {$legacy_name} {$definition}") === false) {
                        return false;
                    }
                    continue;
                }

                if (
                    $wpdb->query(
                        "UPDATE {$table}
                         SET `{$new_name}` = `{$legacy_name}`
                         WHERE (`{$new_name}` IS NULL OR `{$new_name}` = '')
                           AND `{$legacy_name}` IS NOT NULL
                           AND `{$legacy_name}` <> ''"
                    ) === false
                ) {
                    return false;
                }

                if ($wpdb->query("ALTER TABLE {$table} DROP COLUMN `{$legacy_name}`") === false) {
                    // Legacy indexes can block dropping old columns; remove those and retry once.
                    if (!$this->drop_indexes_for_column($wpdb, $table, $legacy_name)) {
                        return false;
                    }

                    if ($wpdb->query("ALTER TABLE {$table} DROP COLUMN `{$legacy_name}`") === false) {
                        return false;
                    }
                }
            }
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        if (!$this->apply_subscription_saas_schema_dbdelta($wpdb)) {
            return false;
        }

        return true;
    }

    private function drop_indexes_for_column(\wpdb $wpdb, string $table, string $column): bool {
        $indexes = $wpdb->get_results(
            $wpdb->prepare("SHOW INDEX FROM {$table} WHERE Column_name = %s", $column),
            ARRAY_A
        );

        if (!is_array($indexes) || $indexes === []) {
            return true;
        }

        $dropped = [];
        foreach ($indexes as $index_row) {
            $key_name = isset($index_row['Key_name']) ? (string) $index_row['Key_name'] : '';
            if ($key_name === '' || strtoupper($key_name) === 'PRIMARY' || isset($dropped[$key_name])) {
                continue;
            }

            $safe_key = str_replace('`', '``', $key_name);
            if ($wpdb->query("ALTER TABLE {$table} DROP INDEX `{$safe_key}`") === false) {
                return false;
            }

            $dropped[$key_name] = true;
        }

        return true;
    }

    private function apply_subscription_saas_schema_dbdelta(\wpdb $wpdb): bool {
        $p = $wpdb->base_prefix;
        $collate = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$p}myvh_plans (
            id                    BIGINT UNSIGNED AUTO_INCREMENT,
            PRIMARY KEY (id),
            plan_key              VARCHAR(100) NOT NULL,
            name                  VARCHAR(150) NOT NULL,
            description           TEXT NULL,
            billing_interval      VARCHAR(20) NOT NULL DEFAULT 'monthly',
            price                 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            booking_limit         BIGINT NULL,
            features              LONGTEXT NULL,
            currency_code         CHAR(3) NOT NULL DEFAULT 'GBP',
            stripe_price_id_monthly VARCHAR(191) NULL,
            is_active             TINYINT(1) NOT NULL DEFAULT 1,
            metadata              LONGTEXT NULL,
            created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_plan_key (plan_key),
            INDEX idx_is_active (is_active),
            INDEX idx_stripe_price_monthly (stripe_price_id_monthly)
        ) {$collate};");

        dbDelta("CREATE TABLE {$p}myvh_accounts (
            id                    BIGINT UNSIGNED AUTO_INCREMENT,
            PRIMARY KEY (id),
            blog_id               BIGINT UNSIGNED NULL,
            owner_user_id         BIGINT UNSIGNED NULL,
            plan_id               BIGINT UNSIGNED NULL,
            account_name          VARCHAR(150) NOT NULL,
            contact_email         VARCHAR(191) NOT NULL DEFAULT '',
            status                VARCHAR(30) NOT NULL DEFAULT 'active',
            external_reference    VARCHAR(191) NULL,
            metadata              LONGTEXT NULL,
            created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_blog_id (blog_id),
            UNIQUE KEY uq_external_reference (external_reference),
            INDEX idx_owner_user (owner_user_id),
            INDEX idx_plan_id (plan_id),
            INDEX idx_status (status)
        ) {$collate};");

        dbDelta("CREATE TABLE {$p}myvh_subscriptions (
            id                    BIGINT UNSIGNED AUTO_INCREMENT,
            PRIMARY KEY (id),
            account_id            BIGINT UNSIGNED NOT NULL,
            plan_id               BIGINT UNSIGNED NULL,
            plan_code             VARCHAR(100) NOT NULL,
            booking_limit_snapshot BIGINT NULL,
            features_snapshot     LONGTEXT NULL,
            status                VARCHAR(30) NOT NULL DEFAULT 'active',
            provider              VARCHAR(50) NULL,
            stripe_customer_id    VARCHAR(191) NULL,
            stripe_subscription_id VARCHAR(191) NULL,
            provider_subscription_id VARCHAR(191) NULL,
            started_at            DATETIME NULL,
            current_period_start  DATETIME NULL,
            current_period_end    DATETIME NULL,
            trial_ends_at         DATETIME NULL,
            cancel_at             DATETIME NULL,
            canceled_at           DATETIME NULL,
            metadata              LONGTEXT NULL,
            created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_provider_subscription_id (provider_subscription_id),
            UNIQUE KEY uq_stripe_subscription_id (stripe_subscription_id),
            INDEX idx_account_id (account_id),
            INDEX idx_plan_id (plan_id),
            INDEX idx_plan_code (plan_code),
            INDEX idx_stripe_customer_id (stripe_customer_id),
            INDEX idx_status (status),
            INDEX idx_period_end (current_period_end)
        ) {$collate};");

        dbDelta("CREATE TABLE {$p}myvh_settings (
            id                    BIGINT UNSIGNED AUTO_INCREMENT,
            PRIMARY KEY (id),
            setting_key           VARCHAR(191) NOT NULL,
            setting_value         LONGTEXT NULL,
            is_autoload           TINYINT(1) NOT NULL DEFAULT 0,
            updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_setting_key (setting_key),
            INDEX idx_setting_key (setting_key),
            INDEX idx_autoload (is_autoload)
        ) {$collate};");

        dbDelta("CREATE TABLE {$p}myvh_usage (
            id                    BIGINT UNSIGNED AUTO_INCREMENT,
            PRIMARY KEY (id),
            account_id            BIGINT UNSIGNED NOT NULL,
            metric_key            VARCHAR(100) NOT NULL,
            metric_period_start   DATETIME NOT NULL,
            metric_period_end     DATETIME NOT NULL,
            quantity              BIGINT NOT NULL DEFAULT 0,
            metadata              LONGTEXT NULL,
            recorded_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_usage_metric_period (account_id, metric_key, metric_period_start, metric_period_end),
            INDEX idx_metric_key (metric_key),
            INDEX idx_period (metric_period_start, metric_period_end)
        ) {$collate};");

        return true;
    }

    private function render_schema_diagnostics(): void {
        $diagnostics = $this->get_schema_diagnostics();

        echo '<hr style="margin:24px 0">';
        echo '<h2>' . esc_html__('Subscription Schema Diagnostics', 'my-village-hall') . '</h2>';
        echo '<p>' . esc_html__('Read-only check for mixed legacy camel-case and canonical snake_case columns in SaaS subscription tables.', 'my-village-hall') . '</p>';

        if ($diagnostics['rows'] === []) {
            echo '<div class="notice notice-warning"><p>'
                . esc_html__('No diagnostics data is available right now.', 'my-village-hall')
                . '</p></div>';
            return;
        }

        if ($diagnostics['has_mixed_columns']) {
            echo '<div class="notice notice-error"><p>'
                . esc_html__('Mixed schema detected. At least one table still has duplicate legacy/canonical columns.', 'my-village-hall')
                . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>'
                . esc_html__('Schema is normalized. No mixed legacy/canonical column pairs were found.', 'my-village-hall')
                . '</p></div>';
        }

        echo '<table class="widefat striped" style="max-width:1000px">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Table', 'my-village-hall') . '</th>';
        echo '<th>' . esc_html__('Mixed pairs', 'my-village-hall') . '</th>';
        echo '<th>' . esc_html__('Legacy-only columns', 'my-village-hall') . '</th>';
        echo '<th>' . esc_html__('Status', 'my-village-hall') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($diagnostics['rows'] as $row) {
            $mixed_pairs = $row['mixed_pairs'];
            $legacy_only = $row['legacy_only'];

            $mixed_text = $mixed_pairs !== []
                ? implode(', ', array_map(static fn(array $pair): string => $pair['legacy'] . ' + ' . $pair['canonical'], $mixed_pairs))
                : __('None', 'my-village-hall');

            $legacy_only_text = $legacy_only !== []
                ? implode(', ', $legacy_only)
                : __('None', 'my-village-hall');

            $status_text = $mixed_pairs !== []
                ? __('Needs cleanup', 'my-village-hall')
                : __('OK', 'my-village-hall');

            echo '<tr>';
            echo '<td>' . esc_html((string) $row['table']) . '</td>';
            echo '<td>' . esc_html($mixed_text) . '</td>';
            echo '<td>' . esc_html($legacy_only_text) . '</td>';
            echo '<td><strong>' . esc_html($status_text) . '</strong></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function get_schema_diagnostics(): array {
        global $wpdb;

        if (!$wpdb instanceof \wpdb) {
            return [
                'has_mixed_columns' => false,
                'rows' => [],
            ];
        }

        $table_prefix = $wpdb->base_prefix;
        $table_maps = [
            'myvh_plans' => [
                'PlanKey' => 'plan_key',
                'BillingInterval' => 'billing_interval',
                'CurrencyCode' => 'currency_code',
                'StripePriceIdMonthly' => 'stripe_price_id_monthly',
                'IsActive' => 'is_active',
                'CreatedAt' => 'created_at',
                'UpdatedAt' => 'updated_at',
            ],
            'myvh_accounts' => [
                'BlogId' => 'blog_id',
                'OwnerUserId' => 'owner_user_id',
                'PlanId' => 'plan_id',
                'AccountName' => 'account_name',
                'ContactEmail' => 'contact_email',
                'ExternalReference' => 'external_reference',
                'CreatedAt' => 'created_at',
                'UpdatedAt' => 'updated_at',
            ],
            'myvh_subscriptions' => [
                'AccountId' => 'account_id',
                'PlanId' => 'plan_id',
                'PlanCode' => 'plan_code',
                'StripeCustomerId' => 'stripe_customer_id',
                'StripeSubscriptionId' => 'stripe_subscription_id',
                'ProviderSubscriptionId' => 'provider_subscription_id',
                'StartedAt' => 'started_at',
                'CurrentPeriodStart' => 'current_period_start',
                'CurrentPeriodEnd' => 'current_period_end',
                'TrialEndsAt' => 'trial_ends_at',
                'CancelAt' => 'cancel_at',
                'CanceledAt' => 'canceled_at',
                'CreatedAt' => 'created_at',
                'UpdatedAt' => 'updated_at',
            ],
            'myvh_settings' => [
                'SettingKey' => 'setting_key',
                'SettingValue' => 'setting_value',
                'IsAutoload' => 'is_autoload',
                'UpdatedAt' => 'updated_at',
            ],
            'myvh_usage' => [
                'AccountId' => 'account_id',
                'MetricKey' => 'metric_key',
                'MetricPeriodStart' => 'metric_period_start',
                'MetricPeriodEnd' => 'metric_period_end',
                'RecordedAt' => 'recorded_at',
            ],
        ];

        $rows = [];
        $has_mixed_columns = false;

        foreach ($table_maps as $table_suffix => $pairs) {
            $table_name = $table_prefix . $table_suffix;
            $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table_name}", 0);
            $columns = is_array($columns) ? array_map('strval', $columns) : [];

            $mixed_pairs = [];
            $legacy_only = [];

            foreach ($pairs as $legacy => $canonical) {
                $has_legacy = in_array($legacy, $columns, true);
                $has_canonical = in_array($canonical, $columns, true);

                if ($has_legacy && $has_canonical) {
                    $mixed_pairs[] = [
                        'legacy' => $legacy,
                        'canonical' => $canonical,
                    ];
                    continue;
                }

                if ($has_legacy && !$has_canonical) {
                    $legacy_only[] = $legacy;
                }
            }

            if ($mixed_pairs !== []) {
                $has_mixed_columns = true;
            }

            $rows[] = [
                'table' => $table_name,
                'mixed_pairs' => $mixed_pairs,
                'legacy_only' => $legacy_only,
            ];
        }

        return [
            'has_mixed_columns' => $has_mixed_columns,
            'rows' => $rows,
        ];
    }

    public function save_billing_settings(): void {
        if (!$this->current_user_can_manage_billing_settings()) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_subscription_billing_settings');

        $trial_days = max(1, (int) ($_POST['trial_days'] ?? 31));
        $grace_period_days = max(0, (int) ($_POST['grace_period_days'] ?? 3));
        $default_plan = sanitize_key((string) ($_POST['default_plan'] ?? 'trial'));

        $this->settings_service->set('trial_days', $trial_days);
        $this->settings_service->set('grace_period_days', $grace_period_days);
        $this->settings_service->set('default_plan', $default_plan);

        wp_safe_redirect(add_query_arg(
            [
                'page' => self::BILLING_SLUG,
                'updated' => 1,
            ],
            admin_url('admin.php')
        ));
        return;
    }

    public function render_upgrade_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        $context = $this->get_account_context();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Upgrade Plan', 'my-village-hall') . '</h1>';

        if (!empty($_GET['plan_changed'])) {
            $notice = !empty($_GET['plan_scheduled'])
                ? __('Plan downgrade has been scheduled for the end of the current billing period.', 'my-village-hall')
                : __('Plan change started successfully.', 'my-village-hall');

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>';
        }

        if (!empty($_GET['plan_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>'
                . esc_html(wp_unslash((string) $_GET['plan_error']))
                . '</p></div>';
        }

        if ($context['account_id'] <= 0) {
            echo '<div class="notice notice-warning"><p>'
                . esc_html__('No account is linked to this site yet.', 'my-village-hall')
                . '</p></div>';
            echo '</div>';
            return;
        }

        if (!$context['subscription'] instanceof Subscription) {
            echo '<div class="notice notice-warning"><p>'
                . esc_html__('No active subscription found for this site.', 'my-village-hall')
                . '</p></div>';
            echo '</div>';
            return;
        }

        $subscription = $context['subscription'];
        $plan_label = $this->resolve_plan_label($subscription);
        $plan_options = $this->plan_change_policy_service->getPlanOptionsForAccount($context['account_id']);

        echo '<table class="widefat striped" style="max-width:900px;margin-bottom:20px;">';
        echo '<tbody>';
        echo '<tr><th style="width:240px">' . esc_html__('Current plan', 'my-village-hall') . '</th><td>' . esc_html($plan_label) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Status', 'my-village-hall') . '</th><td>' . esc_html(ucfirst($subscription->getStatus())) . '</td></tr>';
        echo '</tbody>';
        echo '</table>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:900px;">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::CHANGE_PLAN_ACTION) . '">';
        wp_nonce_field('myvh_subscription_change_plan');

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="myvh-upgrade-plan-code">' . esc_html__('Choose Plan', 'my-village-hall') . '</label></th>';
        echo '<td>';
        echo '<select id="myvh-upgrade-plan-code" name="plan_code" class="regular-text" required>';
        echo '<option value="">' . esc_html__('Select a plan', 'my-village-hall') . '</option>';

        foreach ($plan_options as $option) {
            $plan = $option['plan'] ?? null;
            if (!$plan instanceof Plan) {
                continue;
            }

            $allowed = !empty($option['allowed']);
            $is_current = !empty($option['is_current']);
            $message = trim((string) ($option['message'] ?? ''));

            $label = $plan->getName();
            if ($is_current) {
                $label .= ' (' . __('Current', 'my-village-hall') . ')';
            } elseif (!$allowed && $message !== '') {
                $label .= ' - ' . $message;
            }

            echo '<option value="' . esc_attr($plan->getCode()) . '"' . disabled(!$allowed, true, false) . '>'
                . esc_html($label)
                . '</option>';
        }

        echo '</select>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="myvh-upgrade-payment-method">' . esc_html__('Payment Method', 'my-village-hall') . '</label></th>';
        echo '<td>';
        echo '<select id="myvh-upgrade-payment-method" name="payment_method" class="regular-text" required>';
        echo '<option value="stripe">' . esc_html__('Stripe', 'my-village-hall') . '</option>';
        echo '<option value="invoice">' . esc_html__('Invoice', 'my-village-hall') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        submit_button(__('Change Plan', 'my-village-hall'));
        echo '</form>';

        echo '</div>';
    }

    public function change_plan(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_subscription_change_plan');

        $context = $this->get_account_context();
        if ($context['account_id'] <= 0) {
            $this->redirect_upgrade_with_error(__('No account is linked to this site yet.', 'my-village-hall'));
            return;
        }

        $plan_code = sanitize_key((string) ($_POST['plan_code'] ?? ''));
        $payment_method = sanitize_key((string) ($_POST['payment_method'] ?? 'stripe'));
        if (!in_array($payment_method, ['stripe', 'invoice'], true)) {
            $this->redirect_upgrade_with_error(__('Unsupported payment method.', 'my-village-hall'));
            return;
        }

        $plan = $this->plan_repository->getByCode($plan_code);
        if (!$plan instanceof Plan) {
            $this->redirect_upgrade_with_error(__('Selected plan was not found.', 'my-village-hall'));
            return;
        }

        $decision = $this->plan_change_policy_service->canChangeToPlan($context['account_id'], $plan);
        if (empty($decision['allowed'])) {
            $this->redirect_upgrade_with_error((string) ($decision['message'] ?? __('Plan change is not allowed.', 'my-village-hall')));
            return;
        }

        $result = $this->billing_service->requestPlanChange($context['account_id'], $plan_code, $payment_method);
        if (!is_array($result) || empty($result['success'])) {
            $message = $payment_method === 'stripe'
                ? __('Stripe is not configured for this site yet. Choose Invoice or configure Stripe keys in Billing Settings.', 'my-village-hall')
                : __('Unable to start the plan change flow.', 'my-village-hall');

            $this->redirect_upgrade_with_error($message);
            return;
        }

        wp_safe_redirect(add_query_arg([
            'page' => self::UPGRADE_SLUG,
            'plan_changed' => 1,
            'plan_scheduled' => !empty($result['scheduled']) ? 1 : 0,
        ], admin_url('admin.php')));
        return;
    }

    public function generate_manual_invoice(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_subscription_manual_invoice');

        $context = $this->get_account_context();
        if ($context['account_id'] <= 0) {
            $this->redirect_dashboard_with_error(__('No account is linked to this site yet.', 'my-village-hall'));
            return;
        }

        $payment_method = sanitize_key((string) ($_POST['payment_method'] ?? 'invoice'));
        $result = $this->manual_invoice_service->handlePaymentMethod($context['account_id'], $payment_method);
        if (is_wp_error($result)) {
            $this->redirect_dashboard_with_error((string) $result->get_error_message());
            return;
        }

        wp_safe_redirect(add_query_arg([
            'page' => self::DASHBOARD_SLUG,
            'manual_invoice_generated' => 1,
        ], admin_url('admin.php')));
        return;
    }

    public function mark_manual_invoice_paid(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_subscription_mark_manual_invoice_paid');

        $context = $this->get_account_context();
        if ($context['account_id'] <= 0) {
            $this->redirect_dashboard_with_error(__('No account is linked to this site yet.', 'my-village-hall'));
            return;
        }

        $invoice_id = (int) ($_POST['invoice_id'] ?? 0);
        $result = $this->manual_invoice_service->markInvoicePaid($context['account_id'], $invoice_id);
        if (is_wp_error($result) || $result === false) {
            $message = is_wp_error($result)
                ? (string) $result->get_error_message()
                : __('Failed to mark manual subscription invoice as paid.', 'my-village-hall');

            $this->redirect_dashboard_with_error($message);
            return;
        }

        wp_safe_redirect(add_query_arg([
            'page' => self::DASHBOARD_SLUG,
            'manual_invoice_paid' => 1,
        ], admin_url('admin.php')));
        return;
    }

    public function render_subscription_notices(): void {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || strpos((string) $screen->id, 'myvh') === false) {
            return;
        }

        $context = $this->get_account_context();
        if (!$context['subscription'] instanceof Subscription) {
            return;
        }

        $subscription = $context['subscription'];
        $status = $subscription->getStatus();

        if ($subscription->isExpired()) {
            echo '<div class="notice notice-error"><p>'
                . esc_html__('Subscription is expired. Key actions are currently blocked.', 'my-village-hall')
                . '</p></div>';
            return;
        }

        if (!$subscription->isTrial()) {
            return;
        }

        $trial_end_raw = $subscription->getTrialEndsAt();
        $trial_end_ts = strtotime($trial_end_raw);
        if ($trial_end_ts === false) {
            return;
        }

        $now_ts = current_time('timestamp');
        $seconds_left = $trial_end_ts - $now_ts;
        $days_left = (int) ceil($seconds_left / 86400);

        if ($days_left <= self::NOTICE_TRIAL_WINDOW_DAYS) {
            $account = is_array($context['account']) ? $context['account'] : [];
            $this->billing_notification_service->notifyTrialEndingSoon($account, $subscription, $days_left);

            $message = sprintf(
                __('Trial ends in %d day(s) on %s.', 'my-village-hall'),
                max(0, $days_left),
                $this->format_date($trial_end_raw)
            );

            echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
        }
    }

    private function current_user_can_manage_billing_settings(): bool {
        return function_exists('is_super_admin') && is_super_admin();
    }

    private function get_account_context(?int $blog_id = null, bool $allow_create_account = true): array {
        $blog_id = $blog_id ?? (function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0);
        $account = $blog_id > 0 ? $this->account_service->resolveAccountFromBlogId($blog_id) : null;

        if (!is_array($account) && $blog_id > 0 && $allow_create_account) {
            $site_name = sanitize_text_field((string) get_bloginfo('name'));
            $admin_email = sanitize_email((string) get_option('admin_email', ''));
            if ($site_name !== '' && $admin_email !== '') {
                $account = $this->account_service->createAccount($site_name, $admin_email);
            }
        }

        $account_id = is_array($account) ? (int) ($account['id'] ?? 0) : 0;

        if ($account_id <= 0) {
            return [
                'account_id' => 0,
                'account' => null,
                'subscription' => null,
            ];
        }

        $subscription = $this->trial_service->checkAndExpireTrial($account_id);
        if (!$subscription instanceof Subscription) {
            $subscription = $this->trial_service->createTrialSubscription($account_id);
        }

        return [
            'blog_id' => $blog_id,
            'account_id' => $account_id,
            'account' => $account,
            'subscription' => $subscription,
        ];
    }

    private function resolve_plan_label(Subscription $subscription): string {
        $plan_code = $subscription->getPlanCode();

        if ($plan_code !== '') {
            $plan = $this->plan_repository->getByCode($plan_code);
            if ($plan instanceof Plan) {
                return $plan->getName();
            }

            return $plan_code;
        }

        $plan_id = $subscription->getPlanId();
        if ($plan_id > 0) {
            $plan = $this->plan_repository->get_by_id($plan_id);
            if ($plan instanceof Plan) {
                return $plan->getName();
            }
        }

        return __('Unknown', 'my-village-hall');
    }

    private function format_date(string $raw): string {
        if ($raw === '') {
            return __('N/A', 'my-village-hall');
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return __('N/A', 'my-village-hall');
        }

        return function_exists('date_i18n')
            ? (string) date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)
            : gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function render_subscription_status_editor(array $context, Subscription $subscription): void {
        $status = $subscription->getStatus();
        $blog_id = (int) ($context['blog_id'] ?? 0);

        echo '<hr style="margin:24px 0">';
        echo '<h2>' . esc_html__('Subscription Status', 'my-village-hall') . '</h2>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:620px;margin-bottom:20px;">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::UPDATE_STATUS_ACTION) . '">';
        if ($blog_id > 0) {
            echo '<input type="hidden" name="blog_id" value="' . esc_attr((string) $blog_id) . '">';
        }
        wp_nonce_field('myvh_subscription_update_status');

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr>';
        echo '<th scope="row"><label for="myvh-subscription-status">' . esc_html__('Status', 'my-village-hall') . '</label></th>';
        echo '<td>';
        echo '<select id="myvh-subscription-status" name="subscription_status" class="regular-text">';

        foreach ([
            SubscriptionStatus::TRIALING,
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::PAST_DUE,
            SubscriptionStatus::CANCELLED,
            SubscriptionStatus::EXPIRED,
        ] as $candidate_status) {
            echo '<option value="' . esc_attr($candidate_status) . '" ' . selected($status, $candidate_status, false) . '>'
                . esc_html(ucwords(str_replace('_', ' ', $candidate_status)))
                . '</option>';
        }

        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '</tbody></table>';

        submit_button(__('Save Status', 'my-village-hall'));
        echo '</form>';
    }

    private function render_manual_invoice_actions(array $context, Subscription $subscription): void {
        $status = $subscription->getStatus();
        $metadata = $this->decode_metadata($subscription->getMetadataRaw());
        $pending_invoice_id = (int) ($metadata['manual_invoice_id'] ?? 0);
        $blog_id = (int) ($context['blog_id'] ?? 0);

        echo '<hr style="margin:24px 0">';
        echo '<h2>' . esc_html__('Manual Payment (Invoice)', 'my-village-hall') . '</h2>';

        if ($status === SubscriptionStatus::PAST_DUE && $pending_invoice_id > 0) {
            echo '<p>' . esc_html(sprintf(__('Subscription is awaiting payment for invoice #%d.', 'my-village-hall'), $pending_invoice_id)) . '</p>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="' . esc_attr(self::MARK_MANUAL_INVOICE_PAID_ACTION) . '">';
            echo '<input type="hidden" name="invoice_id" value="' . esc_attr((string) $pending_invoice_id) . '">';
            if ($blog_id > 0) {
                echo '<input type="hidden" name="blog_id" value="' . esc_attr((string) $blog_id) . '">';
            }
            wp_nonce_field('myvh_subscription_mark_manual_invoice_paid');
            submit_button(__('Mark Invoice as Paid', 'my-village-hall'), 'primary', 'submit', false);
            echo '</form>';
            return;
        }

        echo '<p>' . esc_html__('Generate a manual subscription invoice and move the subscription to awaiting payment.', 'my-village-hall') . '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::GENERATE_MANUAL_INVOICE_ACTION) . '">';
        echo '<input type="hidden" name="account_id" value="' . esc_attr((string) $context['account_id']) . '">';
        if ($blog_id > 0) {
            echo '<input type="hidden" name="blog_id" value="' . esc_attr((string) $blog_id) . '">';
        }
        echo '<input type="hidden" name="payment_method" value="invoice">';
        wp_nonce_field('myvh_subscription_manual_invoice');
        submit_button(__('Generate Subscription Invoice', 'my-village-hall'), 'secondary', 'submit', false);
        echo '</form>';
    }

    private function redirect_dashboard_with_error(string $message, int $blog_id = 0): void {
        wp_safe_redirect(add_query_arg([
            'page' => self::DASHBOARD_SLUG,
            'blog_id' => $blog_id > 0 ? $blog_id : null,
            'manual_invoice_error' => $message,
        ], admin_url('admin.php')));
    }

    private function redirect_upgrade_with_error(string $message): void {
        wp_safe_redirect(add_query_arg([
            'page' => self::UPGRADE_SLUG,
            'plan_error' => $message,
        ], admin_url('admin.php')));
    }

    private function decode_metadata(string $metadata_raw): array {
        if ($metadata_raw === '') {
            return [];
        }

        $decoded = json_decode($metadata_raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolve_target_blog_id(): int {
        if (isset($_REQUEST['blog_id'])) {
            return max(0, (int) $_REQUEST['blog_id']);
        }

        return 0;
    }
}
