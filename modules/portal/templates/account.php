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
    <div class="myvh-account-header">
        <div>
            <h2>Account Settings</h2>
            <p>Manage your profile details and sign-in credentials.</p>
        </div>
        <div class="myvh-account-chip"><?php echo esc_html($current_user->user_login); ?></div>
    </div>

    <div class="myvh-account-grid">
        <div class="myvh-card myvh-account-card">
            <div class="myvh-account-card-head">
                <h3>Your Details</h3>
                <span>Public profile and contact information</span>
            </div>

            <form id="myvh-account-details-form" class="myvh-account-form">
                <label class="myvh-account-field" for="myvh-account-name">
                    <span>Name</span>
                    <input id="myvh-account-name" type="text" name="name" required value="<?php echo esc_attr($profile_name); ?>">
                </label>

                <label class="myvh-account-field" for="myvh-account-email">
                    <span>Email</span>
                    <input id="myvh-account-email" type="email" name="email" required value="<?php echo esc_attr($profile_email); ?>">
                </label>

                <label class="myvh-account-field" for="myvh-account-phone">
                    <span>Phone</span>
                    <input id="myvh-account-phone" type="text" name="phone_number" value="<?php echo esc_attr($profile_phone); ?>">
                </label>

                <label class="myvh-account-field" for="myvh-account-address">
                    <span>Address</span>
                    <input id="myvh-account-address" type="text" name="address_line1" value="<?php echo esc_attr($profile_address); ?>">
                </label>

                <label class="myvh-account-field" for="myvh-account-postcode">
                    <span>Post code</span>
                    <input id="myvh-account-postcode" type="text" name="post_code" value="<?php echo esc_attr($profile_post_code); ?>">
                </label>

                <div class="myvh-account-actions">
                    <button type="submit" class="button button-primary">Save Details</button>
                    <div id="myvh-account-details-message" class="myvh-muted" aria-live="polite"></div>
                </div>
            </form>
        </div>

        <div class="myvh-card myvh-account-card">
            <div class="myvh-account-card-head">
                <h3>Security</h3>
                <span>Change the password you use to sign in</span>
            </div>

            <form id="myvh-account-password-form" class="myvh-account-form">
                <label class="myvh-account-field" for="myvh-current-password">
                    <span>Current password</span>
                    <input id="myvh-current-password" type="password" name="current_password" required autocomplete="current-password">
                </label>

                <label class="myvh-account-field" for="myvh-new-password">
                    <span>New password</span>
                    <input id="myvh-new-password" type="password" name="new_password" required minlength="8" autocomplete="new-password">
                </label>

                <label class="myvh-account-field" for="myvh-confirm-password">
                    <span>Confirm new password</span>
                    <input id="myvh-confirm-password" type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
                </label>

                <p class="myvh-account-hint">Use at least 8 characters and avoid reusing old passwords.</p>

                <div class="myvh-account-actions">
                    <button type="submit" class="button button-primary">Update Password</button>
                    <div id="myvh-account-password-message" class="myvh-muted" aria-live="polite"></div>
                </div>
            </form>
        </div>
    </div>
</div>
