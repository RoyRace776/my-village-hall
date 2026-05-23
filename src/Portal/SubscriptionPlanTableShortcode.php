<?php
namespace MYVH\Portal;

if (!defined('ABSPATH')) {
    exit;
}

use MYVH\Core\Shortcode\ShortcodeInterface;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\AccountService;
use MYVH\Subscriptions\Services\PlanChangePolicyService;
use MYVH\Subscriptions\Services\TrialService;

class SubscriptionPlanTableShortcode implements ShortcodeInterface {
    public function __construct(
        private ClientAdminService $client_admin_service,
        private ?AccountRepository $account_repository = null,
        private ?SubscriptionRepository $subscription_repository = null,
        private ?PlanChangePolicyService $plan_change_policy_service = null,
        private ?AccountService $account_service = null,
        private ?TrialService $trial_service = null
    ) {
    }

    public function tag(): string {
        return 'myvh_subscription_plan_table';
    }

    public function render(mixed $atts = [], mixed $content = null): string {
        if (!is_user_logged_in()) {
            return do_shortcode('[myvh_login]');
        }

        if (!$this->client_admin_service->can_administer_blog(get_current_user_id(), get_current_blog_id())) {
            return '<p class="myvh-error">' . esc_html__('Permission denied.', 'my-village-hall') . '</p>';
        }

        if (
            !$this->account_repository instanceof AccountRepository
            || !$this->subscription_repository instanceof SubscriptionRepository
            || !$this->plan_change_policy_service instanceof PlanChangePolicyService
        ) {
            return '<p class="myvh-error">' . esc_html__('Subscription services are unavailable right now.', 'my-village-hall') . '</p>';
        }

        $dashboard_css_path = MYVH_PLUGIN_DIR . 'assets/css/dashboard.css';
        $dashboard_css_version = file_exists($dashboard_css_path) ? (string) filemtime($dashboard_css_path) : MYVH_VERSION;
        wp_enqueue_style(
            'myvh-dashboard',
            MYVH_PLUGIN_URL . 'assets/css/dashboard.css',
            [],
            $dashboard_css_version
        );

        $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
        $account = $blog_id > 0 ? $this->account_repository->get_by_blog_id($blog_id) : null;

        if (!is_array($account) && $blog_id > 0 && $this->account_service instanceof AccountService) {
            $account = $this->account_service->resolveAccountFromBlogId($blog_id);
        }

        if (!is_array($account) && $this->account_service instanceof AccountService) {
            $site_name = sanitize_text_field((string) get_bloginfo('name'));
            $admin_email = sanitize_email((string) get_option('admin_email', ''));
            if ($site_name !== '' && $admin_email !== '') {
                $account = $this->account_service->createAccount($site_name, $admin_email);
            }
        }

        $account_id = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
        if ($account_id <= 0) {
            return '<p class="myvh-error">' . esc_html__('No billing account is linked to this site yet.', 'my-village-hall') . '</p>';
        }

        $subscription = $this->subscription_repository->get_active_by_account_id($account_id);
        if (
            !$subscription instanceof Subscription
            && $this->trial_service instanceof TrialService
        ) {
            $subscription = $this->trial_service->createTrialSubscription($account_id);
        }

        if (!$subscription instanceof Subscription) {
            return '<p class="myvh-error">' . esc_html__('No active subscription found for this site.', 'my-village-hall') . '</p>';
        }

        $plan_options = $this->plan_change_policy_service->getPlanOptionsForAccount($account_id);

        $scheduled_plan_code = '';
        $scheduled_effective_at = '';
        $metadata = json_decode($subscription->getMetadataRaw(), true);
        $scheduled_plan_change = is_array($metadata) && is_array($metadata['scheduled_plan_change'] ?? null)
            ? $metadata['scheduled_plan_change']
            : null;

        if (is_array($scheduled_plan_change)) {
            $scheduled_plan_code = sanitize_key((string) ($scheduled_plan_change['plan_code'] ?? ''));
            $scheduled_effective_at = (string) ($scheduled_plan_change['effective_at'] ?? '');
        }

        $plan_comparison_rows = [];
        foreach ($plan_options as $option) {
            $plan = $option['plan'] ?? null;
            if (!$plan instanceof Plan) {
                continue;
            }

            $features = $plan->getFeatures();
            $raw_booking_limit = $features['booking_limit'] ?? $features['bookings'] ?? null;
            $booking_limit = $plan->getBookingLimit();
            $booking_allowance = $booking_limit === null
                ? __('Unlimited', 'my-village-hall')
                : sprintf(
                    /* translators: %d is the monthly booking allowance for a plan. */
                    __('%d per month', 'my-village-hall'),
                    $booking_limit
                );

            if (($raw_booking_limit === null || $raw_booking_limit === '') && $booking_limit === null) {
                $booking_allowance = __('Not specified', 'my-village-hall');
            }

            $price = $plan->getPrice();
            $price_label = $price <= 0
                ? __('Free', 'my-village-hall')
                : sprintf(
                    /* translators: %s is the formatted monthly plan price. */
                    __('£%s', 'my-village-hall'),
                    number_format_i18n($price, 2)
                );

            $billing_interval = trim((string) $plan->getBillingInterval());
            $billing_label = $billing_interval === ''
                ? __('Monthly', 'my-village-hall')
                : ucwords(str_replace('_', ' ', $billing_interval));

            $offerings = [];
            foreach ($features as $feature_key => $feature_value) {
                if (!is_string($feature_key) || in_array($feature_key, ['booking_limit', 'bookings'], true)) {
                    continue;
                }

                $label_key = sanitize_key($feature_key);
                if ($label_key === '') {
                    continue;
                }

                $feature_label = ucwords(str_replace('_', ' ', $label_key));

                if (is_bool($feature_value)) {
                    if ($feature_value) {
                        $offerings[] = $feature_label;
                    }
                    continue;
                }

                if (is_numeric($feature_value) && (float) $feature_value > 0) {
                    $offerings[] = sprintf(
                        /* translators: 1: feature label, 2: feature numeric value. */
                        __('%1$s: %2$s', 'my-village-hall'),
                        $feature_label,
                        number_format_i18n((float) $feature_value, 0)
                    );
                    continue;
                }

                if (is_string($feature_value)) {
                    $normalized = strtolower(trim($feature_value));
                    if ($normalized === '' || $normalized === 'false' || $normalized === 'no' || $normalized === '0') {
                        continue;
                    }

                    if ($normalized === 'true' || $normalized === 'yes') {
                        $offerings[] = $feature_label;
                        continue;
                    }

                    $offerings[] = sprintf(
                        /* translators: 1: feature label, 2: feature text value. */
                        __('%1$s: %2$s', 'my-village-hall'),
                        $feature_label,
                        $feature_value
                    );
                }
            }

            if ($offerings === []) {
                $offerings[] = __('Standard plan access', 'my-village-hall');
            }

            $plan_code = $plan->getCode();
            $is_current = !empty($option['is_current']);
            $allowed = !empty($option['allowed']);
            $is_scheduled = $scheduled_plan_code !== '' && $scheduled_plan_code === $plan_code;
            $status_text = $is_scheduled
                ? __('Scheduled change', 'my-village-hall')
                : ($is_current
                    ? __('Current plan', 'my-village-hall')
                    : ($allowed ? __('Available', 'my-village-hall') : __('Unavailable', 'my-village-hall')));

            $status_hint = trim((string) ($option['message'] ?? ''));
            if ($is_scheduled && $scheduled_effective_at !== '') {
                $status_hint = sprintf(
                    /* translators: %s is the effective date/time of a scheduled plan change. */
                    __('Effective on %s', 'my-village-hall'),
                    mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $scheduled_effective_at)
                );
            }

            $plan_comparison_rows[] = [
                'name' => $plan->getName(),
                'price' => $price_label,
                'billing' => $billing_label,
                'booking_allowance' => $booking_allowance,
                'offerings' => $offerings,
                'status' => $status_text,
                'status_hint' => $status_hint,
            ];
        }

        if ($plan_comparison_rows === []) {
            return '<p class="myvh-account-hint">' . esc_html__('No plans are currently available.', 'my-village-hall') . '</p>';
        }

        ob_start();
        ?>
        <div class="myvh-plan-comparison">
            <h3><?php esc_html_e('Available Plans', 'my-village-hall'); ?></h3>
            <p class="myvh-account-hint"><?php esc_html_e('Compare what each plan offers before changing your subscription.', 'my-village-hall'); ?></p>

            <div class="myvh-plan-comparison-wrap">
                <table class="myvh-plan-comparison-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Plan', 'my-village-hall'); ?></th>
                            <th scope="col"><?php esc_html_e('Price', 'my-village-hall'); ?></th>
                            <th scope="col"><?php esc_html_e('Billing', 'my-village-hall'); ?></th>
                            <th scope="col"><?php esc_html_e('Booking allowance', 'my-village-hall'); ?></th>
                            <th scope="col"><?php esc_html_e('What is included', 'my-village-hall'); ?></th>
                            <th scope="col"><?php esc_html_e('Availability', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plan_comparison_rows as $row): ?>
                            <tr>
                                <td><?php echo esc_html((string) $row['name']); ?></td>
                                <td><?php echo esc_html((string) $row['price']); ?></td>
                                <td><?php echo esc_html((string) $row['billing']); ?></td>
                                <td><?php echo esc_html((string) $row['booking_allowance']); ?></td>
                                <td>
                                    <ul class="myvh-plan-offerings">
                                        <?php foreach ((array) $row['offerings'] as $offering): ?>
                                            <li><?php echo esc_html((string) $offering); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                                <td>
                                    <strong><?php echo esc_html((string) $row['status']); ?></strong>
                                    <?php if ((string) $row['status_hint'] !== ''): ?>
                                        <div class="myvh-account-hint"><?php echo esc_html((string) $row['status_hint']); ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}