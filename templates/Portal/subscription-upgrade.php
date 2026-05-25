<?php
if (!defined('ABSPATH')) exit;

$is_client_admin = !empty($is_client_admin);
$account_id = isset($account_id) ? (int) $account_id : 0;
$current_plan_label = isset($current_plan_label) ? (string) $current_plan_label : __('Unknown', 'my-village-hall');
$subscription = (isset($subscription) && $subscription instanceof \MYVH\Subscriptions\Entities\Subscription)
    ? $subscription
    : null;
$subscription_status = ($subscription instanceof \MYVH\Subscriptions\Entities\Subscription)
    ? (string) $subscription->getStatus()
    : '';
$plan_options = (isset($plan_options) && is_array($plan_options)) ? $plan_options : [];
$trial_days = isset($trial_days) ? max(0, (int) $trial_days) : 0;
$is_wordpress_super_user = !empty($is_wordpress_super_user);

$trial_start_date_value = '';
if ($subscription instanceof \MYVH\Subscriptions\Entities\Subscription) {
    $trial_start_raw = $subscription->getCurrentPeriodStart();
    $trial_start_ts = $trial_start_raw !== '' ? strtotime($trial_start_raw) : false;
    if ($trial_start_ts !== false) {
        $trial_start_date_value = gmdate('Y-m-d', $trial_start_ts);
    }
}

$trial_days_remaining = null;
if ($subscription instanceof \MYVH\Subscriptions\Entities\Subscription && $subscription_status === 'trialing') {
    $trial_ends_at = $subscription->getTrialEndsAt();
    if ($trial_ends_at !== '') {
        $trial_end_ts = strtotime($trial_ends_at);
        if ($trial_end_ts === false) {
            $trial_days_remaining = null;
        } else {
            $now_ts = (int) current_time('timestamp');
            $seconds_left = $trial_end_ts - $now_ts;
            $trial_days_remaining = max(0, (int) ceil($seconds_left / 86400));
        }
    }
}

$can_update_trial_start_date = false;
if ($subscription instanceof \MYVH\Subscriptions\Entities\Subscription) {
    $can_update_trial_start_date = $subscription->isTrial()
        || ($subscription->isExpired() && $subscription->getTrialEndsAt() !== '');
}

$scheduled_plan_code = '';
$scheduled_plan_label = '';
$scheduled_effective_at = '';
if ($subscription instanceof \MYVH\Subscriptions\Entities\Subscription) {
    $metadata = json_decode($subscription->getMetadataRaw(), true);
    $scheduled_plan_change = is_array($metadata) && is_array($metadata['scheduled_plan_change'] ?? null)
        ? $metadata['scheduled_plan_change']
        : null;

    if (is_array($scheduled_plan_change)) {
        $scheduled_plan_code = sanitize_key((string) ($scheduled_plan_change['plan_code'] ?? ''));
        $scheduled_effective_at = (string) ($scheduled_plan_change['effective_at'] ?? '');
        $scheduled_plan_label = $scheduled_plan_code;
    }
}

$plan_comparison_rows = [];
foreach ($plan_options as $option) {
    $plan = $option['plan'] ?? null;
    if (!$plan instanceof \MYVH\Subscriptions\Entities\Plan) {
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
    $is_scheduled = $scheduled_plan_code !== '' && $scheduled_plan_code === $plan_code;
    $trial_length = $trial_days > 0
        ? sprintf(
            /* translators: %d is the number of trial days. */
            _n('%d day', '%d days', $trial_days, 'my-village-hall'),
            $trial_days
        )
        : __('No trial', 'my-village-hall');

    if ($is_scheduled) {
        $scheduled_plan_label = $plan->getName();
    }

    $plan_comparison_rows[] = [
        'code' => $plan_code,
        'name' => $plan->getName(),
        'price' => $price_label,
        'billing' => $billing_label,
        'trial_length' => $trial_length,
        'booking_allowance' => $booking_allowance,
        'offerings' => $offerings,
    ];
}
?>

<div class="myvh-dashboard-section myvh-account-page">
    <div class="myvh-account-header">
        <div>
            <h2><?php esc_html_e('Manage Subscription Plan', 'my-village-hall'); ?></h2>
            <p><?php esc_html_e('View your current plan and request an upgrade or downgrade.', 'my-village-hall'); ?></p>
        </div>
        <div class="myvh-account-chip"><?php echo esc_html($current_plan_label); ?></div>
    </div>

    <div class="myvh-card myvh-account-card">
        <?php if (!$is_client_admin): ?>
            <p class="myvh-error"><?php esc_html_e('Permission denied.', 'my-village-hall'); ?></p>
        <?php elseif ($account_id <= 0): ?>
            <p class="myvh-error"><?php esc_html_e('No billing account is linked to this site yet.', 'my-village-hall'); ?></p>
        <?php elseif (!$subscription instanceof \MYVH\Subscriptions\Entities\Subscription): ?>
            <p class="myvh-error"><?php esc_html_e('No active subscription found for this site.', 'my-village-hall'); ?></p>
        <?php else: ?>
            <p><strong><?php esc_html_e('Current plan:', 'my-village-hall'); ?></strong> <?php echo esc_html($current_plan_label); ?></p>
            <p><strong><?php esc_html_e('Status:', 'my-village-hall'); ?></strong> <?php echo esc_html(ucfirst($subscription_status)); ?></p>

            <?php if ($trial_days_remaining !== null): ?>
                <?php
                $trial_badge_class = 'myvh-trial-badge--danger';
                if ($trial_days_remaining > 7) {
                    $trial_badge_class = 'myvh-trial-badge--success';
                } elseif ($trial_days_remaining > 2) {
                    $trial_badge_class = 'myvh-trial-badge--warning';
                }
                ?>
                <div class="myvh-trial-badge-wrap">
                    <span class="myvh-trial-badge <?php echo esc_attr($trial_badge_class); ?>">
                        <?php if ($trial_days_remaining === 0): ?>
                            <?php esc_html_e('Trial expired', 'my-village-hall'); ?>
                        <?php elseif ($trial_days_remaining === 1): ?>
                            <?php esc_html_e('1 day remaining in trial', 'my-village-hall'); ?>
                        <?php else: ?>
                            <?php
                            printf(
                                esc_html__('%d days remaining in trial', 'my-village-hall'),
                                $trial_days_remaining
                            );
                            ?>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($scheduled_plan_code !== ''): ?>
                <p>
                    <strong><?php esc_html_e('Pending change:', 'my-village-hall'); ?></strong>
                    <?php echo esc_html($scheduled_plan_label !== '' ? $scheduled_plan_label : $scheduled_plan_code); ?>
                    <?php if ($scheduled_effective_at !== ''): ?>
                        <?php
                        $effective_display = mysql2date(
                            get_option('date_format') . ' ' . get_option('time_format'),
                            $scheduled_effective_at
                        );
                        ?>
                        <span class="myvh-account-hint"><?php echo esc_html(sprintf(__('Effective on %s', 'my-village-hall'), $effective_display)); ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if ($is_wordpress_super_user && $can_update_trial_start_date): ?>
                <form class="myvh-account-form" data-portal-action="myvh_portal_update_trial_start_date" data-message-target="myvh-trial-start-message" data-reload-page="subscription-upgrade">
                    <label class="myvh-account-field" for="myvh-trial-start-date">
                        <span><?php esc_html_e('Trial start date (Super User)', 'my-village-hall'); ?></span>
                        <input
                            type="date"
                            id="myvh-trial-start-date"
                            name="trial_start_date"
                            value="<?php echo esc_attr($trial_start_date_value); ?>"
                            required
                        >
                    </label>
                    <div class="myvh-account-actions">
                        <button type="submit" class="myvh-portal-add-btn">
                            <span><?php esc_html_e('Update Trial Start Date', 'my-village-hall'); ?></span>
                        </button>
                        <div id="myvh-trial-start-message" class="myvh-muted" aria-live="polite"></div>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($plan_comparison_rows !== []): ?>
                <div class="myvh-plan-comparison">
                    <h3><?php esc_html_e('Available Plans', 'my-village-hall'); ?></h3>
                    <p class="myvh-account-hint"><?php esc_html_e('Compare what each plan offers before changing your subscription.', 'my-village-hall'); ?></p>

                    <div class="myvh-plan-comparison-wrap">
                        <table class="myvh-plan-comparison-table">
                            <caption class="screen-reader-text"><?php esc_html_e('Subscription plan comparison', 'my-village-hall'); ?></caption>
                            <thead>
                                <tr>
                                    <th scope="col"><?php esc_html_e('Plan', 'my-village-hall'); ?></th>
                                    <th scope="col"><?php esc_html_e('Price', 'my-village-hall'); ?></th>
                                    <th scope="col"><?php esc_html_e('Billing', 'my-village-hall'); ?></th>
                                    <th scope="col"><?php esc_html_e('Trial length', 'my-village-hall'); ?></th>
                                    <th scope="col"><?php esc_html_e('Booking allowance', 'my-village-hall'); ?></th>
                                    <th scope="col"><?php esc_html_e('What is included', 'my-village-hall'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plan_comparison_rows as $row): ?>
                                    <tr>
                                        <th scope="row" class="myvh-plan-name"><?php echo esc_html((string) $row['name']); ?></th>
                                        <td class="myvh-plan-price"><?php echo esc_html((string) $row['price']); ?></td>
                                        <td><?php echo esc_html((string) $row['billing']); ?></td>
                                        <td><?php echo esc_html((string) $row['trial_length']); ?></td>
                                        <td><?php echo esc_html((string) $row['booking_allowance']); ?></td>
                                        <td>
                                            <ul class="myvh-plan-offerings">
                                                <?php foreach ((array) $row['offerings'] as $offering): ?>
                                                    <li><?php echo esc_html((string) $offering); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <form class="myvh-account-form" data-portal-action="myvh_portal_change_subscription_plan" data-message-target="myvh-subscription-plan-message" data-reload-page="subscription-upgrade">
                <label class="myvh-account-field" for="myvh-subscription-plan-code">
                    <span><?php esc_html_e('Choose a plan', 'my-village-hall'); ?></span>
                    <select id="myvh-subscription-plan-code" name="plan_code" required>
                        <option value=""><?php esc_html_e('Select a plan', 'my-village-hall'); ?></option>
                        <?php foreach ($plan_options as $option): ?>
                            <?php
                            $plan = $option['plan'] ?? null;
                            if (!$plan instanceof \MYVH\Subscriptions\Entities\Plan) {
                                continue;
                            }
                            $is_current = !empty($option['is_current']);
                            $allowed = !empty($option['allowed']);
                            $is_scheduled = $scheduled_plan_code !== '' && $scheduled_plan_code === $plan->getCode();
                            $hint = trim((string) ($option['message'] ?? ''));
                            $label = $plan->getName();
                            if ($is_current) {
                                $label .= ' ' . __('(Current)', 'my-village-hall');
                            }
                            if ($is_scheduled) {
                                $label .= ' ' . __('(Scheduled)', 'my-village-hall');
                            } elseif (!$allowed && $hint !== '') {
                                $label .= ' - ' . $hint;
                            }
                            ?>
                            <option value="<?php echo esc_attr($plan->getCode()); ?>" <?php disabled(!$allowed); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="myvh-account-field" for="myvh-subscription-payment-method">
                    <span><?php esc_html_e('Payment method', 'my-village-hall'); ?></span>
                    <select id="myvh-subscription-payment-method" name="payment_method" required>
                        <option value="stripe"><?php esc_html_e('Stripe', 'my-village-hall'); ?></option>
                        <option value="invoice"><?php esc_html_e('Invoice', 'my-village-hall'); ?></option>
                    </select>
                </label>

                <div class="myvh-account-actions">
                    <button type="submit" class="myvh-portal-add-btn">
                        <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
                        <span><?php esc_html_e('Change Plan', 'my-village-hall'); ?></span>
                    </button>
                    <div id="myvh-subscription-plan-message" class="myvh-muted" aria-live="polite"></div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
