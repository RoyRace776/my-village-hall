<?php
if (!defined('ABSPATH')) exit;

$is_client_admin = !empty($is_client_admin);
$current_user = wp_get_current_user();
$profile_name = !empty($customer['Name']) ? $customer['Name'] : $current_user->display_name;
$profile_email = !empty($customer['Email']) ? $customer['Email'] : $current_user->user_email;
$profile_phone = !empty($customer['PhoneNumber']) ? $customer['PhoneNumber'] : '';
$profile_address = !empty($customer['AddressLine1']) ? $customer['AddressLine1'] : '';
$profile_post_code = !empty($customer['PostCode']) ? $customer['PostCode'] : '';
?>

<div class="myvh-dashboard-section myvh-account-page">
    <div class="myvh-account-header">
        <div>
            <h2>Account Settings</h2>
            <p><?php echo $is_client_admin ? 'Manage your portal details and sign-in credentials for this client.' : 'Manage your profile details and sign-in credentials.'; ?></p>
        </div>
        <div class="myvh-account-chip"><?php echo esc_html($current_user->user_login); ?></div>
    </div>

    <div class="myvh-account-grid">
        <div class="myvh-card myvh-account-card">
            <div class="myvh-account-card-head">
                <h3>Your Details</h3>
                <span><?php echo !empty($customer['Id']) ? 'Public profile and contact information' : 'WordPress profile details'; ?></span>
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
                    <button type="submit" class="myvh-portal-add-btn">
                        <span class="myvh-portal-add-btn__icon" aria-hidden="true">✓</span>
                        <span>Save Details</span>
                    </button>
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
                    <div class="myvh-password-field">
                        <input id="myvh-current-password" type="password" name="current_password" required autocomplete="current-password">
                        <button
                            type="button"
                            class="myvh-password-toggle"
                            data-password-toggle
                            data-show-label="Show password"
                            data-hide-label="Hide password"
                            aria-controls="myvh-current-password"
                            aria-label="Show password"
                            aria-pressed="false"
                        >
                            <span class="myvh-password-toggle__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="myvh-password-toggle__eye">
                                    <path d="M1.5 12C3.4 7.9 7.3 5.25 12 5.25C16.7 5.25 20.6 7.9 22.5 12C20.6 16.1 16.7 18.75 12 18.75C7.3 18.75 3.4 16.1 1.5 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="3.25" stroke="currentColor" stroke-width="1.8"/>
                                </svg>
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="myvh-password-toggle__eye-off">
                                    <path d="M3 3L21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    <path d="M1.5 12C2.6 9.6 4.4 7.58 6.65 6.28M10.6 5.36C11.05 5.29 11.52 5.25 12 5.25C16.7 5.25 20.6 7.9 22.5 12C21.56 14.03 20.18 15.71 18.5 16.94M14.86 18.35C13.94 18.62 12.98 18.75 12 18.75C7.3 18.75 3.4 16.1 1.5 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </button>
                    </div>
                </label>

                <label class="myvh-account-field" for="myvh-new-password">
                    <span>New password</span>
                    <div class="myvh-password-field">
                        <input id="myvh-new-password" type="password" name="new_password" required minlength="9" autocomplete="new-password">
                        <button
                            type="button"
                            class="myvh-password-toggle"
                            data-password-toggle
                            data-show-label="Show password"
                            data-hide-label="Hide password"
                            aria-controls="myvh-new-password"
                            aria-label="Show password"
                            aria-pressed="false"
                        >
                            <span class="myvh-password-toggle__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="myvh-password-toggle__eye">
                                    <path d="M1.5 12C3.4 7.9 7.3 5.25 12 5.25C16.7 5.25 20.6 7.9 22.5 12C20.6 16.1 16.7 18.75 12 18.75C7.3 18.75 3.4 16.1 1.5 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="3.25" stroke="currentColor" stroke-width="1.8"/>
                                </svg>
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="myvh-password-toggle__eye-off">
                                    <path d="M3 3L21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    <path d="M1.5 12C2.6 9.6 4.4 7.58 6.65 6.28M10.6 5.36C11.05 5.29 11.52 5.25 12 5.25C16.7 5.25 20.6 7.9 22.5 12C21.56 14.03 20.18 15.71 18.5 16.94M14.86 18.35C13.94 18.62 12.98 18.75 12 18.75C7.3 18.75 3.4 16.1 1.5 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </button>
                    </div>
                </label>

                <label class="myvh-account-field" for="myvh-confirm-password">
                    <span>Confirm new password</span>
                    <div class="myvh-password-field">
                        <input id="myvh-confirm-password" type="password" name="confirm_password" required minlength="9" autocomplete="new-password">
                        <button
                            type="button"
                            class="myvh-password-toggle"
                            data-password-toggle
                            data-show-label="Show password"
                            data-hide-label="Hide password"
                            aria-controls="myvh-confirm-password"
                            aria-label="Show password"
                            aria-pressed="false"
                        >
                            <span class="myvh-password-toggle__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="myvh-password-toggle__eye">
                                    <path d="M1.5 12C3.4 7.9 7.3 5.25 12 5.25C16.7 5.25 20.6 7.9 22.5 12C20.6 16.1 16.7 18.75 12 18.75C7.3 18.75 3.4 16.1 1.5 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="3.25" stroke="currentColor" stroke-width="1.8"/>
                                </svg>
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="myvh-password-toggle__eye-off">
                                    <path d="M3 3L21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    <path d="M1.5 12C2.6 9.6 4.4 7.58 6.65 6.28M10.6 5.36C11.05 5.29 11.52 5.25 12 5.25C16.7 5.25 20.6 7.9 22.5 12C21.56 14.03 20.18 15.71 18.5 16.94M14.86 18.35C13.94 18.62 12.98 18.75 12 18.75C7.3 18.75 3.4 16.1 1.5 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </button>
                    </div>
                </label>

                <p class="myvh-account-hint">Use at least 9 characters with uppercase, lowercase, number, and symbol.</p>

                <div class="myvh-account-actions">
                    <button type="submit" class="myvh-portal-add-btn">
                        <span class="myvh-portal-add-btn__icon" aria-hidden="true">✓</span>
                        <span>Update Password</span>
                    </button>
                    <div id="myvh-account-password-message" class="myvh-muted" aria-live="polite"></div>
                </div>
            </form>
        </div>
    </div>
</div>
