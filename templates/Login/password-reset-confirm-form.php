<?php
if (!defined('ABSPATH')) exit;
$error = get_transient('myvh_reset_error');
delete_transient('myvh_reset_error');
$success = get_transient('myvh_reset_success');
delete_transient('myvh_reset_success');
$login_page = get_page_by_path('login');
$login_url = $login_page ? get_permalink($login_page->ID) : home_url('/login/');
?>
<div class="myvh-login-container">
    <div class="myvh-login-shell">
        <header class="myvh-login-header">
            <?php
            $myvh_portal_logo_url = trim((string) myvh_setting('general.portal_logo_url', ''));
            if ($myvh_portal_logo_url !== ''): ?>
                <div class="myvh-login-logo"><img src="<?php echo esc_url($myvh_portal_logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"></div>
            <?php elseif (has_custom_logo()): ?>
                <div class="myvh-login-logo"><?php echo get_custom_logo(); ?></div>
            <?php endif; ?>
            <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
            <p>Enter your new password below.</p>
        </header>
        <div class="myvh-login-columns myvh-login-columns--single">
            <form method="post" class="myvh-login-form myvh-login-form--reset-confirm">
                <p class="myvh-form-subtitle">Choose a strong password for your account.</p>
                <?php if ($error): ?>
                    <div class="myvh-error-message">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"/>
                            <path d="M10 6v4m0 2v.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span><?php echo esc_html($error); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="myvh-login-success"><span><?php echo esc_html($success); ?></span></div>
                <?php endif; ?>
                <?php wp_nonce_field('myvh_reset_confirm', 'myvh_reset_confirm_nonce'); ?>
                <div class="myvh-form-group">
                    <label for="myvh-new-password">New password</label>
                    <div class="myvh-password-field">
                        <input id="myvh-new-password" type="password" name="new_password" required autocomplete="new-password" class="myvh-login-input">
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
                </div>
                <div class="myvh-form-group">
                    <label for="myvh-new-password-confirm">Confirm new password</label>
                    <div class="myvh-password-field">
                        <input id="myvh-new-password-confirm" type="password" name="confirm_password" required autocomplete="new-password" class="myvh-login-input">
                        <button
                            type="button"
                            class="myvh-password-toggle"
                            data-password-toggle
                            data-show-label="Show password"
                            data-hide-label="Hide password"
                            aria-controls="myvh-new-password-confirm"
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
                </div>
                <p class="myvh-password-hint">Use at least 9 characters with uppercase, lowercase, number, and symbol.</p>
                <button type="submit" class="myvh-login-button">Set password</button>
                <div class="myvh-form-footer myvh-form-footer--secondary">
                    <a href="<?php echo esc_url($login_url); ?>">Back to sign in</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.myvh-login-form--reset-confirm');
    if (!form) {
        return;
    }

    var toggleButtons = form.querySelectorAll('[data-password-toggle]');
    if (toggleButtons.length) {
        toggleButtons.forEach(function (button) {
            var inputId = button.getAttribute('aria-controls');
            if (!inputId) {
                return;
            }

            var input = document.getElementById(inputId);
            if (!input) {
                return;
            }

            button.addEventListener('click', function () {
                var shouldShow = input.type === 'password';
                input.type = shouldShow ? 'text' : 'password';

                button.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
                button.setAttribute(
                    'aria-label',
                    shouldShow ? (button.getAttribute('data-hide-label') || 'Hide password') : (button.getAttribute('data-show-label') || 'Show password')
                );
            });
        });
    }

    var passwordInput = form.querySelector('[name="new_password"]');
    var confirmInput = form.querySelector('[name="confirm_password"]');
    if (!passwordInput || !confirmInput) {
        return;
    }

    function validateMatch() {
        if (passwordInput.value !== confirmInput.value) {
            confirmInput.setCustomValidity('Passwords do not match.');
            return false;
        }

        confirmInput.setCustomValidity('');
        return true;
    }

    passwordInput.addEventListener('input', validateMatch);
    confirmInput.addEventListener('input', validateMatch);

    form.addEventListener('submit', function (event) {
        if (!validateMatch()) {
            event.preventDefault();
            confirmInput.reportValidity();
        }
    });
});
</script>
