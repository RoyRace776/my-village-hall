<?php
if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
$profile_name = !empty($customer['Name']) ? $customer['Name'] : $current_user->display_name;
$profile_email = !empty($customer['Email']) ? $customer['Email'] : $current_user->user_email;
$profile_phone = !empty($customer['PhoneNumber']) ? $customer['PhoneNumber'] : '';
$profile_address = !empty($customer['AddressLine1']) ? $customer['AddressLine1'] : '';
$profile_post_code = !empty($customer['PostCode']) ? $customer['PostCode'] : '';
?>

<div class="myvh-dashboard-section">
    <div class="myvh-section-header">
        <h2>Account</h2>
    </div>

    <div class="myvh-card">
        <h3 style="margin-bottom:12px;">Your Details</h3>
        <form id="myvh-account-details-form">
            <table class="form-table" style="max-width:760px;">
                <tr>
                    <th><label for="myvh-account-name">Name</label></th>
                    <td><input id="myvh-account-name" type="text" name="name" required value="<?php echo esc_attr($profile_name); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="myvh-account-email">Email</label></th>
                    <td><input id="myvh-account-email" type="email" name="email" required value="<?php echo esc_attr($profile_email); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="myvh-account-phone">Phone</label></th>
                    <td><input id="myvh-account-phone" type="text" name="phone_number" value="<?php echo esc_attr($profile_phone); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="myvh-account-address">Address</label></th>
                    <td><input id="myvh-account-address" type="text" name="address_line1" value="<?php echo esc_attr($profile_address); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="myvh-account-postcode">Post Code</label></th>
                    <td><input id="myvh-account-postcode" type="text" name="post_code" value="<?php echo esc_attr($profile_post_code); ?>" class="regular-text"></td>
                </tr>
            </table>

            <p>
                <button type="submit" class="button button-primary">Save Details</button>
            </p>
            <div id="myvh-account-details-message" class="myvh-muted" aria-live="polite"></div>
        </form>
    </div>

    <div class="myvh-card">
        <h3 style="margin-bottom:12px;">Change Password</h3>
        <form id="myvh-account-password-form">
            <table class="form-table" style="max-width:760px;">
                <tr>
                    <th><label for="myvh-current-password">Current Password</label></th>
                    <td><input id="myvh-current-password" type="password" name="current_password" required class="regular-text" autocomplete="current-password"></td>
                </tr>
                <tr>
                    <th><label for="myvh-new-password">New Password</label></th>
                    <td><input id="myvh-new-password" type="password" name="new_password" required minlength="8" class="regular-text" autocomplete="new-password"></td>
                </tr>
                <tr>
                    <th><label for="myvh-confirm-password">Confirm Password</label></th>
                    <td><input id="myvh-confirm-password" type="password" name="confirm_password" required minlength="8" class="regular-text" autocomplete="new-password"></td>
                </tr>
            </table>

            <p>
                <button type="submit" class="button button-primary">Update Password</button>
            </p>
            <div id="myvh-account-password-message" class="myvh-muted" aria-live="polite"></div>
        </form>
    </div>
</div>
