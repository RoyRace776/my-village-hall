<?php
namespace MYVH\Login;

use MYVH\Email\EmailService;
/**
 * PasswordResetHandler: Handles custom password reset requests and confirmations
 */
class PasswordResetHandler {
    protected $email_service;
    protected $token_ttl = 60 * 60; // 1 hour

    public function __construct($email_service = null) {
        $this->email_service = $email_service ?: new EmailService();
    }

    public function init() {
        add_action('init', [$this, 'handle_reset_request']);
        add_action('init', [$this, 'handle_reset_confirm']);
    }

    /**
     * Handle password reset request form submission
     */
    public function handle_reset_request() {
        if (!isset($_POST['myvh_reset_request_nonce'])) return;
        if (!wp_verify_nonce($_POST['myvh_reset_request_nonce'], 'myvh_reset_request')) wp_die('Invalid request.');
        $email = sanitize_email($_POST['reset_email'] ?? '');
        if (!$email || !is_email($email)) {
            set_transient('myvh_reset_error', 'Please enter a valid email.', 30);
            wp_safe_redirect($_SERVER['REQUEST_URI']); exit;
        }
        $user = get_user_by('email', $email);
        if (!$user) {
            set_transient('myvh_reset_error', 'No user found with that email.', 30);
            wp_safe_redirect($_SERVER['REQUEST_URI']); exit;
        }
        $token = $this->generate_token($user->ID);
        $reset_base = $this->get_reset_page_url();
        $reset_url = add_query_arg([
            'myvh_reset' => 1,
            'uid' => $user->ID,
            'token' => $token
        ], $reset_base);
        $branding = $this->email_service->get_branding();
        $this->email_service->send([
            'to' => $email,
            'subject' => 'Reset your password',
            'template' => 'password-reset',
            'template_vars' => [
                'reset_url' => $reset_url,
                'logo_url' => $branding['logo_url'],
                'site_name' => $branding['site_name'],
                'site_url' => $branding['site_url'],
            ],
        ]);
        set_transient('myvh_reset_success', 'Check your email for a password reset link.', 60);
        wp_safe_redirect($_SERVER['REQUEST_URI']); exit;
    }

    /**
     * Handle password reset confirmation (link from email)
     */
    public function handle_reset_confirm() {
        if (empty($_GET['myvh_reset']) || empty($_GET['uid']) || empty($_GET['token'])) return;
        $user_id = intval($_GET['uid']);
        // Only keep alphanumeric chars — sanitize_text_field strips %XX sequences
        // which can corrupt tokens if the raw value contained a literal '%'.
        $token = preg_replace('/[^a-zA-Z0-9]/', '', (string) $_GET['token']);
        if (!$this->validate_token($user_id, $token)) {
            set_transient('myvh_reset_error', 'Invalid or expired reset link.', 60);
            wp_safe_redirect(home_url('/login/')); exit;
        }
        if (isset($_POST['myvh_reset_confirm_nonce'])) {
            if (!wp_verify_nonce($_POST['myvh_reset_confirm_nonce'], 'myvh_reset_confirm')) wp_die('Invalid request.');
            $password = $_POST['new_password'] ?? '';
            $error = $this->validate_password($password);
            if ($error) {
                set_transient('myvh_reset_error', $error, 30);
                wp_safe_redirect($_SERVER['REQUEST_URI']); exit;
            }
            wp_set_password($password, $user_id);
            $this->invalidate_token($user_id);
            set_transient('myvh_reset_success', 'Password updated. You can now sign in.', 60);
            wp_safe_redirect(home_url('/login/')); exit;
        }
    }

    /**
     * Generate a secure, single-use token for password reset.
     * Uses hex encoding so the value is always URL-safe and survives
     * sanitize_text_field without modification.
     */
    protected function generate_token($user_id) {
        $token = bin2hex(random_bytes(16)); // 32-char lowercase hex string
        set_transient('myvh_reset_token_' . $user_id, $token, $this->token_ttl);
        return $token;
    }

    /**
     * Validate a password reset token
     */
    protected function validate_token($user_id, $token) {
        $saved = get_transient('myvh_reset_token_' . $user_id);
        return $saved && hash_equals($saved, $token);
    }

    /**
     * Invalidate a password reset token (after use or password change)
     */
    public function invalidate_token($user_id) {
        delete_transient('myvh_reset_token_' . $user_id);
    }

    /**
     * Validate password strength (reuse login handler logic if possible)
     */
    protected function validate_password($password) {
        if (strlen($password) < 10) return 'Password must be at least 10 characters.';
        if (!preg_match('/[A-Z]/', $password)) return 'Password must include at least one uppercase letter.';
        if (!preg_match('/[a-z]/', $password)) return 'Password must include at least one lowercase letter.';
        if (!preg_match('/\d/', $password)) return 'Password must include at least one number.';
        if (!preg_match('/[^A-Za-z0-9]/', $password)) return 'Password must include at least one symbol.';
        return null;
    }

    /**
     * Invalidate token on password change (hooked externally)
     */
    public function hook_invalidate_on_change() {
        add_action('after_password_reset', function($user) {
            $this->invalidate_token($user->ID);
        });
    }

    /**
     * Resolve the most likely password reset page URL.
     */
    public function get_reset_page_url(): string {
        $filtered = apply_filters('myvh_password_reset_page_url', '');
        if (is_string($filtered) && $filtered !== '') {
            return esc_url_raw($filtered);
        }

        $candidate_pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
            'suppress_filters' => false,
        ]);

        foreach ((array) $candidate_pages as $page) {
            if (empty($page->ID)) {
                continue;
            }

            if (has_shortcode((string) $page->post_content, 'myvh_password_reset')) {
                return (string) get_permalink((int) $page->ID);
            }
        }

        $reset_page = get_page_by_path('password-reset');
        if ($reset_page && !empty($reset_page->ID)) {
            return (string) get_permalink((int) $reset_page->ID);
        }

        foreach (['login', 'sign-in', 'signin', 'account-login'] as $slug) {
            $login_page = get_page_by_path($slug);
            if ($login_page && !empty($login_page->ID)) {
                return (string) get_permalink((int) $login_page->ID);
            }
        }

        return home_url('/login/');
    }
}
