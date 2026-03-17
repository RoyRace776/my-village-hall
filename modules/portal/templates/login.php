<?php
if (!defined('ABSPATH')) exit;

$error = get_transient('myvh_login_error');
delete_transient('myvh_login_error');
?>

<div class="myvh-login-container">
    <form method="post" class="myvh-login-form">

        <h2>Login to My Plugin</h2>

        <?php if ($error): ?>
            <div class="myvh-error-message">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"/>
                    <path d="M10 6v4m0 2v.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span><?php echo esc_html($error); ?></span>
            </div>
        <?php endif; ?>

        <?php wp_nonce_field('myvh_login_action', 'myvh_login_nonce'); ?>

        <div class="myvh-form-group">
            <label for="myvh-username">Username</label>
            <input type="text" id="myvh-username" name="username" required>
        </div>

        <div class="myvh-form-group">
            <label for="myvh-password">Password</label>
            <input type="password" id="myvh-password" name="password" required>
        </div>

        <div class="myvh-form-group myvh-checkbox-group">
            <label>
                <input type="checkbox" name="remember">
                <span>Remember Me</span>
            </label>
        </div>

        <button type="submit" class="myvh-login-button">Login</button>

        <div class="myvh-form-footer">
            <a href="<?php echo esc_url(wp_lostpassword_url()); ?>">Forgot Password?</a>
        </div>

    </form>
</div>
