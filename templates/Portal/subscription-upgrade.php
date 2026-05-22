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
                            $hint = trim((string) ($option['message'] ?? ''));
                            $label = $plan->getName();
                            if ($is_current) {
                                $label .= ' ' . __('(Current)', 'my-village-hall');
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
