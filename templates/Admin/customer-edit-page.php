<?php
if (!defined('ABSPATH')) exit;

use MYVH\Customers\CustomerService;

$customer_id = isset($customer_id) ? intval($customer_id) : 0;
$customer_service = $GLOBALS['myvh_container']->get(CustomerService::class);
$customer = $customer_service->get($customer_id);

if (!$customer) {
    echo '<div class="notice notice-error"><p>Customer not found.</p></div>';
    return;
}
?>
<div class="wrap myvh-admin-customer-edit">
    <h1>Edit Customer</h1>
    <form method="post" action="" class="myvh-admin-form">
        <input type="hidden" name="customer_id" value="<?php echo (int)$customer['Id']; ?>">
        <table class="form-table">
            <tr>
                <th><label for="name">Name</label></th>
                <td><input name="name" id="name" type="text" value="<?php echo esc_attr($customer['Name']); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="email">Email</label></th>
                <td><input name="email" id="email" type="email" value="<?php echo esc_attr($customer['Email']); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="phone_number">Phone</label></th>
                <td><input name="phone_number" id="phone_number" type="text" value="<?php echo esc_attr($customer['PhoneNumber']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="post_code">Post Code</label></th>
                <td><input name="post_code" id="post_code" type="text" value="<?php echo esc_attr($customer['PostCode']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="address_line1">Address</label></th>
                <td><input name="address_line1" id="address_line1" type="text" value="<?php echo esc_attr($customer['AddressLine1']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th>Email Verified</th>
                <td><input name="email_verified" type="checkbox" value="1" <?php checked(!empty($customer['EmailVerified'])); ?>></td>
            </tr>
            <tr>
                <th>Allow Auto Confirm</th>
                <td><input name="allow_auto_confirm" type="checkbox" value="1" <?php checked(!empty($customer['AllowAutoConfirm'])); ?>></td>
            </tr>
        </table>
        <?php submit_button('Save Changes'); ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=myvh-customers')); ?>" class="button">Cancel</a>
    </form>
</div>
<style>
.myvh-admin-customer-edit .form-table th {
    width: 160px;
}
.myvh-admin-customer-edit .form-table input[type="text"],
.myvh-admin-customer-edit .form-table input[type="email"] {
    width: 320px;
}
.myvh-admin-customer-edit .button {
    margin-top: 8px;
}
</style>
