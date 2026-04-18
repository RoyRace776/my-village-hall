<?php
namespace MYVH\Login;

use Exception;
use MYVH\Customers\CustomerService;

class LoginHandler {

    private function delete_user_safe(int $user_id): void {
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        wp_delete_user($user_id);
    }

    private function validate_password_strength(string $password): ?string {
        if (strlen($password) < 9) {
            return 'Password must be at least 9 characters.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must include at least one uppercase letter.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must include at least one lowercase letter.';
        }

        if (!preg_match('/\d/', $password)) {
            return 'Password must include at least one number.';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Password must include at least one symbol.';
        }

        return null;
    }

    public function init(): void {
        add_action('init', [$this, 'handle_login']);
        add_action('init', [$this, 'handle_registration']);
    }

    public function handle_login(): void {

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

        wp_redirect(home_url('/portal'));
        exit;
    }

    public function handle_registration(): void {

        if (!isset($_POST['myvh_register_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['myvh_register_nonce'], 'myvh_register_action')) {
            wp_die('Invalid request.');
        }

        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone_number'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm_password = (string) ($_POST['confirm_password'] ?? '');

        set_transient('myvh_register_prefill_name', $name, 120);
        set_transient('myvh_register_prefill_email', $email, 120);
        set_transient('myvh_register_prefill_phone', $phone, 120);

        delete_transient('myvh_register_existing_email');
        delete_transient('myvh_login_prefill_username');

        if ($name === '') {
            set_transient('myvh_register_error', 'Name is required.', 30);
            return;
        }

        if ($email === '' || !is_email($email)) {
            set_transient('myvh_register_error', 'A valid email address is required.', 30);
            return;
        }

        $password_error = $this->validate_password_strength($password);
        if ($password_error !== null) {
            set_transient('myvh_register_error', $password_error, 30);
            return;
        }

        if ($password !== $confirm_password) {
            set_transient('myvh_register_error', 'Password confirmation does not match.', 30);
            return;
        }

        if (email_exists($email)) {
            set_transient('myvh_register_error', 'An account with this email already exists.', 30);
            set_transient('myvh_register_existing_email', $email, 120);
            set_transient('myvh_login_prefill_username', $email, 120);
            return;
        }

        $role = get_role('myvh_customer') ? 'myvh_customer' : get_option('default_role', 'subscriber');

        $user_id = wp_insert_user([
            'user_login' => $email,
            'user_email' => $email,
            'user_pass'  => $password,
            'display_name' => $name,
            'role' => $role,
        ]);

        if (is_wp_error($user_id)) {
            set_transient('myvh_register_error', $user_id->get_error_message(), 30);
            return;
        }

        try {
            global $myvh_container;

            $customer_service = $myvh_container->get(CustomerService::class);
            $existing_customer = $customer_service->get_by_email($email);

            if ($existing_customer && !empty($existing_customer['Id'])) {
                $saved = $customer_service->save([
                    'customer_id' => (int) $existing_customer['Id'],
                    'name' => $name,
                    'email' => $email,
                    'phone_number' => $phone,
                    'email_verified' => true,
                    'user_id' => (int) $user_id,
                ]);
            } else {
                $saved = $customer_service->save([
                    'name' => $name,
                    'email' => $email,
                    'phone_number' => $phone,
                    'email_verified' => true,
                    'user_id' => (int) $user_id,
                ]);
            }

            if (is_wp_error($saved)) {
                $this->delete_user_safe((int) $user_id);
                set_transient('myvh_register_error', $saved->get_error_message(), 30);
                return;
            }
        } catch (Exception $e) {
            $this->delete_user_safe((int) $user_id);
            set_transient('myvh_register_error', $e->getMessage(), 30);
            return;
        }

        wp_set_current_user((int) $user_id);
        wp_set_auth_cookie((int) $user_id, true);

        delete_transient('myvh_register_prefill_name');
        delete_transient('myvh_register_prefill_email');
        delete_transient('myvh_register_prefill_phone');
        delete_transient('myvh_login_prefill_username');

        $portal_page = get_page_by_path('portal');
        $redirect_url = $portal_page ? get_permalink($portal_page->ID) : home_url('/portal');

        wp_redirect($redirect_url);
        exit;
    }
}
