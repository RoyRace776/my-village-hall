
<?php
if (!defined('ABSPATH')) exit;
if (!isset($is_client_admin)) $is_client_admin = false;

?>
<div class="myvh-dashboard-section myvh-customers-page">
    <div class="myvh-account-header">
        <div>
            <h2>Add Customer</h2>
            <p>Create a new customer profile and link/create a WordPress user as needed.</p>
        </div>
    </div>

    <div class="myvh-card myvh-account-card">
        <form class="myvh-account-form" data-portal-action="myvh_portal_save_customer" data-message-target="myvh-customer-create-message" data-reload-page="customers">
            <label class="myvh-account-field" for="myvh-customer-create-name">
                <span>Name</span>
                <input id="myvh-customer-create-name" type="text" name="name" required>
            </label>

            <label class="myvh-account-field" for="myvh-customer-create-email">
                <span>Email</span>
                <input id="myvh-customer-create-email" type="email" name="email" required>
            </label>

            <div class="myvh-account-grid">
                <label class="myvh-account-field" for="myvh-customer-create-phone">
                    <span>Phone</span>
                    <input id="myvh-customer-create-phone" type="text" name="phone_number">
                </label>

                <label class="myvh-account-field" for="myvh-customer-create-postcode">
                    <span>Post code</span>
                    <input id="myvh-customer-create-postcode" type="text" name="post_code">
                </label>
            </div>

            <label class="myvh-account-field" for="myvh-customer-create-address">
                <span>Address</span>
                <input id="myvh-customer-create-address" type="text" name="address_line1">
            </label>

            <label class="myvh-account-field myvh-room-toggle">
                <input type="checkbox" name="email_verified" value="1">
                <span class="myvh-room-toggle-copy">Mark email as verified</span>
            </label>
            <?php if (!empty($is_client_admin)): ?>
            <label class="myvh-account-field myvh-room-toggle">
                <input type="checkbox" name="allow_auto_confirm" value="1">
                <span class="myvh-room-toggle-copy">Allow Auto Confirm</span>
            </label>
            <label class="myvh-account-field">
                <span>Single booking auto-invoice rule</span>
                <select name="single_booking_auto_invoice_rule_id">
                    <option value="0">Use default rule</option>
                    <?php foreach (($single_booking_rule_options ?? []) as $rule_id => $rule_name): ?>
                        <option value="<?php echo esc_attr((int) $rule_id); ?>"><?php echo esc_html($rule_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="myvh-account-field">
                <span>Recurring booking auto-invoice rule</span>
                <select name="recurring_booking_auto_invoice_rule_id">
                    <option value="0">Use default rule</option>
                    <?php foreach (($recurring_booking_rule_options ?? []) as $rule_id => $rule_name): ?>
                        <option value="<?php echo esc_attr((int) $rule_id); ?>"><?php echo esc_html($rule_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>

            <div class="myvh-account-actions">
                <button type="submit" class="button button-primary">Create Customer</button>
                <div id="myvh-customer-create-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>
