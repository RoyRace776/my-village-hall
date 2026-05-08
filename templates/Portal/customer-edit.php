<?php
if (!defined('ABSPATH')) exit;

use MYVH\Customers\CustomerService;

$customer_id = isset($customer_id) ? intval($customer_id) : 0;
$customer_service = $GLOBALS['myvh_container']->get(CustomerService::class);
$customer = $customer_service->get($customer_id);

if (!$customer) {
    echo '<div class="myvh-card myvh-error"><p>Customer not found.</p></div>';
    return;
}

$created_display = '—';
if (!empty($customer['Created'])) {
    $created_ts = strtotime((string) $customer['Created']);
    if ($created_ts !== false) {
        $created_display = wp_date(get_option('date_format'), $created_ts);
    }
}

$updated_display = '—';
if (!empty($customer['Updated'])) {
    $updated_ts = strtotime((string) $customer['Updated']);
    if ($updated_ts !== false) {
        $updated_display = wp_date(get_option('date_format'), $updated_ts);
    }
}
?>
<div class="myvh-dashboard-section myvh-customers-page">
    <div class="myvh-account-header">
        <div>
            <h2>Edit Customer</h2>
            <p>Update customer details and save changes.</p>
            <p class="myvh-account-hint">Created: <?php echo esc_html($created_display); ?> | Last updated: <?php echo esc_html($updated_display); ?></p>
        </div>
    </div>
    <div class="myvh-card myvh-account-card">
        <form class="myvh-account-form" data-portal-action="myvh_portal_save_customer" data-message-target="myvh-customer-edit-message" data-reload-page="customers">
            <input type="hidden" name="customer_id" value="<?php echo (int)$customer['Id']; ?>">
            <label class="myvh-account-field">
                <span>Name</span>
                <input type="text" name="name" required value="<?php echo esc_attr($customer['Name']); ?>">
            </label>
            <label class="myvh-account-field">
                <span>Email</span>
                <input type="email" name="email" required value="<?php echo esc_attr($customer['Email']); ?>">
            </label>
            <div class="myvh-account-grid">
                <label class="myvh-account-field">
                    <span>Phone</span>
                    <input type="text" name="phone_number" value="<?php echo esc_attr($customer['PhoneNumber']); ?>">
                </label>
                <label class="myvh-account-field">
                    <span>Post code</span>
                    <input type="text" name="post_code" value="<?php echo esc_attr($customer['PostCode']); ?>">
                </label>
            </div>
            <label class="myvh-account-field">
                <span>Address</span>
                <input type="text" name="address_line1" value="<?php echo esc_attr($customer['AddressLine1']); ?>">
            </label>
            <label class="myvh-account-field myvh-room-toggle">
                <input type="checkbox" name="email_verified" value="1" <?php checked(!empty($customer['EmailVerified'])); ?>>
                <span class="myvh-room-toggle-copy">Email verified</span>
            </label>
            <?php if (!empty($is_client_admin)): ?>
            <label class="myvh-account-field myvh-room-toggle">
                <input type="checkbox" name="allow_auto_confirm" value="1" <?php checked(!empty($customer['AllowAutoConfirm'])); ?>>
                <span class="myvh-room-toggle-copy">Allow Auto Confirm</span>
            </label>
            <?php endif; ?>
            <div class="myvh-account-actions">
                <button type="submit" class="button button-primary">Update Customer</button>
                <a href="#customers" class="button">Cancel</a>
                <button type="button" class="button myvh-send-password-reset-btn" data-customer-id="<?php echo (int)$customer['Id']; ?>">Send Password Reset Email</button>
                <div id="myvh-customer-edit-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>
