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
            <p class="myvh-login-kicker"><?php echo esc_html(get_bloginfo('name')); ?></p>
            <h1>Forgot your password?</h1>
            <p>Enter your email address and we'll send you a link to reset your password.</p>
        </header>
        <div class="myvh-login-columns myvh-login-columns--single">
            <form method="post" class="myvh-login-form myvh-login-form--reset">
                <p class="myvh-form-subtitle">We'll email you a link to set a new password.</p>
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
                <?php wp_nonce_field('myvh_reset_request', 'myvh_reset_request_nonce'); ?>
                <div class="myvh-form-group">
                    <label for="myvh-reset-email">Email address</label>
                    <input id="myvh-reset-email" type="email" name="reset_email" required autocomplete="email" class="myvh-login-input">
                </div>
                <button type="submit" class="myvh-login-button">Send reset link</button>
                <div class="myvh-form-footer myvh-form-footer--secondary">
                    <a href="<?php echo esc_url($login_url); ?>">Back to sign in</a>
                </div>
            </form>
        </div>
    </div>
</div>
