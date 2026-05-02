<?php
if (!defined('ABSPATH')) exit;

$error = get_transient('myvh_login_error');
delete_transient('myvh_login_error');

$register_error = get_transient('myvh_register_error');
delete_transient('myvh_register_error');

$existing_email = get_transient('myvh_register_existing_email');
delete_transient('myvh_register_existing_email');

$login_prefill_username = get_transient('myvh_login_prefill_username');
delete_transient('myvh_login_prefill_username');

$register_prefill_name = get_transient('myvh_register_prefill_name');
delete_transient('myvh_register_prefill_name');

$register_prefill_email = get_transient('myvh_register_prefill_email');
delete_transient('myvh_register_prefill_email');

$register_prefill_phone = get_transient('myvh_register_prefill_phone');
delete_transient('myvh_register_prefill_phone');

$mode = strtolower((string)($atts['mode'] ?? 'both'));
if (!in_array($mode, ['login', 'register', 'both'], true)) {
    $mode = 'both';
}

$query_register = !empty($_GET['register']) && $_GET['register'] === '1';
$show_login_form = ($mode === 'both' || $mode === 'login') && !$query_register;
$show_register_form = ($mode === 'both' || $mode === 'register') || $query_register;

$is_register_only = $show_register_form && !$show_login_form;
$is_login_only = $show_login_form && !$show_register_form;

$hero_title = 'Welcome back';
$hero_message = 'Sign in to manage bookings, or create a customer account to get started.';

if ($is_register_only) {
    $hero_title = 'Create your account';
    $hero_message = 'Set up a customer account to request and manage bookings.';
} elseif ($is_login_only) {
    $hero_title = 'Sign in to your account';
    $hero_message = 'Access your portal to manage bookings and account details.';
}

$current_url = get_permalink() ?: home_url('/');

$normalize_local_shortcode_url = static function (string $url): string {
    $url = esc_url_raw($url);
    if ($url === '') {
        return '';
    }

    $current_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    $target_host = wp_parse_url($url, PHP_URL_HOST);

    if (!is_string($current_host) || !is_string($target_host) || $current_host === '' || $target_host === '') {
        return $url;
    }

    $host_suffix = '.local';
    $target_is_local = substr($target_host, -strlen($host_suffix)) === $host_suffix;
    if (!$target_is_local || strcasecmp($target_host, $current_host) === 0) {
        return $url;
    }

    $path = wp_parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return $url;
    }

    $normalized_url = home_url($path);

    $query = wp_parse_url($url, PHP_URL_QUERY);
    if (is_string($query) && $query !== '') {
        parse_str($query, $query_args);
        if (!empty($query_args)) {
            $normalized_url = add_query_arg($query_args, $normalized_url);
        }
    }

    $fragment = wp_parse_url($url, PHP_URL_FRAGMENT);
    if (is_string($fragment) && $fragment !== '') {
        $normalized_url .= '#' . $fragment;
    }

    return $normalized_url;
};

$login_page_url = !empty($atts['login_page_url']) ? $normalize_local_shortcode_url((string)$atts['login_page_url']) : '';
if ($login_page_url === '') {
    $login_page = get_page_by_path('login');
    $login_page_url = $login_page ? get_permalink($login_page->ID) : remove_query_arg('register', $current_url);
}

$register_page_url = !empty($atts['register_page_url']) ? $normalize_local_shortcode_url((string)$atts['register_page_url']) : '';
if ($register_page_url === '') {
    foreach (['register', 'create-account', 'signup', 'sign-up'] as $slug) {
        $register_page = get_page_by_path($slug);
        if ($register_page) {
            $register_page_url = get_permalink($register_page->ID);
            break;
        }
    }
}

if ($register_page_url === '') {
    $register_page_url = add_query_arg('register', '1', $current_url) . '#myvh-register-form';
}

$show_register_focus = $query_register || $mode === 'register';

$show_reset_request = !empty($_GET['reset']) && $_GET['reset'] === '1';
$show_reset_confirm = !empty($_GET['myvh_reset']) && !empty($_GET['uid']) && !empty($_GET['token']);

if ($show_reset_confirm) {
    include MYVH_PLUGIN_DIR . 'templates/Login/password-reset-confirm-form.php';
    return;
}

if ($show_reset_request) {
    include MYVH_PLUGIN_DIR . 'templates/Login/password-reset-form.php';
    return;
}
?>

<div class="myvh-login-container">
    <div class="myvh-login-shell">
    <header class="myvh-login-header">
        <p class="myvh-login-kicker">My Village Hall</p>
        <h1><?php echo esc_html($hero_title); ?></h1>
        <p><?php echo esc_html($hero_message); ?></p>
    </header>

    <div class="myvh-login-columns<?php echo (!$show_login_form || !$show_register_form) ? ' myvh-login-columns--single' : ''; ?>">
    <?php if ($show_login_form): ?>
    <form method="post" class="myvh-login-form myvh-login-form--signin">

        <h2>Sign in</h2>
        <p class="myvh-form-subtitle">Use your existing account credentials.</p>

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
            <input type="text" id="myvh-username" name="username" required value="<?php echo esc_attr($login_prefill_username ?: ''); ?>">
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

        <button type="submit" class="myvh-login-button">Sign in</button>

        <div class="myvh-form-footer">
            <a href="<?php echo esc_url(add_query_arg('reset', '1', $login_page_url)); ?>">Forgot Password?</a>
        </div>

        <div class="myvh-form-footer myvh-form-footer--secondary">
            <a href="<?php echo esc_url($register_page_url); ?>">Need an account? Create one</a>
        </div>

    </form>
    <?php endif; ?>

    <?php if ($show_register_form): ?>
    <form method="post" id="myvh-register-form" class="myvh-login-form myvh-register-form<?php echo $show_register_focus ? ' myvh-register-focus' : ''; ?>">

        <h2>Create account</h2>
        <p class="myvh-form-subtitle">Set up a customer profile for the portal.</p>

        <?php if ($register_error): ?>
            <div class="myvh-error-message">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"/>
                    <path d="M10 6v4m0 2v.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span><?php echo esc_html($register_error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($existing_email): ?>
            <div class="myvh-note-message">
                <span>This email is already registered.</span>
                <a href="#myvh-username" class="myvh-note-action">Use it to log in</a>
            </div>
        <?php endif; ?>

        <?php wp_nonce_field('myvh_register_action', 'myvh_register_nonce'); ?>

        <div class="myvh-form-group">
            <label for="myvh-register-name">Full name</label>
            <input type="text" id="myvh-register-name" name="name" required value="<?php echo esc_attr($register_prefill_name ?: ''); ?>">
        </div>

        <div class="myvh-form-group">
            <label for="myvh-register-email">Email</label>
            <input type="email" id="myvh-register-email" name="email" required value="<?php echo esc_attr($register_prefill_email ?: ''); ?>">
        </div>

        <div class="myvh-form-group">
            <label for="myvh-register-phone">Phone (optional)</label>
            <input type="text" id="myvh-register-phone" name="phone_number" value="<?php echo esc_attr($register_prefill_phone ?: ''); ?>">
        </div>

        <div class="myvh-form-group">
            <label for="myvh-register-password">Password</label>
            <input type="password" id="myvh-register-password" name="password" minlength="9" required>
        </div>

        <div class="myvh-form-group">
            <label for="myvh-register-password-confirm">Confirm password</label>
            <input type="password" id="myvh-register-password-confirm" name="confirm_password" minlength="9" required>
        </div>

        <p class="myvh-password-hint">Use at least 9 characters with uppercase, lowercase, number, and symbol.</p>

        <button type="submit" class="myvh-login-button">Create Account</button>

        <div class="myvh-form-footer myvh-form-footer--secondary">
            <a href="<?php echo esc_url($login_page_url); ?>">Already have an account? Sign in</a>
        </div>
    </form>
    <?php endif; ?>
    </div>
    </div>
</div>
