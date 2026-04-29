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
            <p class="myvh-login-kicker">My Village Hall</p>
            <h1>Set a new password</h1>
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
                    <input id="myvh-new-password" type="password" name="new_password" required autocomplete="new-password" class="myvh-login-input">
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
