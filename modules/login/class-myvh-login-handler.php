<?php

class MYVH_Login_Handler {

    public function init() {
        add_action('init', [$this, 'handle_login']);
    }

    public function handle_login() {

        if (!isset($_POST['myvh_login_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['myvh_login_nonce'], 'myvh_login_action')) {
            wp_die('Invalid request.');
        }

        $username = sanitize_text_field($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $creds = [
            'user_login' => $username,
            'user_password' => $password,
            'remember' => isset($_POST['remember']),
        ];

        $user = wp_signon($creds, false);

        if (is_wp_error($user)) {
            set_transient('myvh_login_error', $user->get_error_message(), 30);
            return;
        }

        wp_redirect(home_url('/dashboard'));
        exit;
    }
}
