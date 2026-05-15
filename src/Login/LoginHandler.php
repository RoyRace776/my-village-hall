<?php
namespace MYVH\Login;

use Exception;
use Throwable;
use MYVH\Customers\CustomerService;
use MYVH\Events\CustomerEvents;
use MYVH\Events\EventDispatcher;

class LoginHandler {
    public function __construct(
        private PasswordValidator $password_validator,
        private ?CustomerEmailVerificationService $verification_service = null
    ) {}

    private function get_verification_service(): CustomerEmailVerificationService {
        if ($this->verification_service instanceof CustomerEmailVerificationService) {
            return $this->verification_service;
        }

        $this->verification_service = new CustomerEmailVerificationService();

        return $this->verification_service;
    }

    private function delete_user_safe(int $user_id): void {
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        wp_delete_user($user_id);
    }

    public function init(): void {
        add_action('init', [$this, 'handle_login']);
        add_action('init', [$this, 'handle_registration']);
        add_action('init', [$this, 'handle_email_verification']);
    }

    private function resolve_login_page_url(): string {
        $fallback = home_url('/login/');

        $filtered = apply_filters('myvh_login_page_url', '');
        if (is_string($filtered) && $filtered !== '') {
            return esc_url_raw($filtered);
        }

        $template_pages = get_pages([
            'post_type' => 'page',
            'post_status' => 'publish',
            'meta_key' => '_wp_page_template',
            'meta_value' => 'templates/page-login.php',
            'number' => 1,
        ]);

        if (!empty($template_pages) && !empty($template_pages[0]->ID)) {
            return (string) get_permalink((int) $template_pages[0]->ID);
        }

        $candidate_pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'menu_order title',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);

        foreach ((array) $candidate_pages as $page) {
            if (empty($page->ID)) {
                continue;
            }

            if (has_shortcode((string) $page->post_content, 'myvh_login')) {
                return (string) get_permalink((int) $page->ID);
            }
        }

        foreach (['login', 'sign-in', 'signin', 'account-login'] as $slug) {
            $candidate = get_page_by_path($slug);
            if ($candidate && !empty($candidate->ID)) {
                return (string) get_permalink((int) $candidate->ID);
            }
        }

        return $fallback;
    }

    private function redirect_registration_error(string $message): void {
        set_transient('myvh_register_error', $message, 30);

        $target = add_query_arg('register', '1', $this->resolve_login_page_url()) . '#myvh-register-form';
        wp_safe_redirect($target);
        exit;
    }

    private function resolve_portal_page_url(): string {
        $fallback = home_url('/portal/');

        $filtered = apply_filters('myvh_portal_page_url', '');
        if (is_string($filtered) && $filtered !== '') {
            return esc_url_raw($filtered);
        }

        $portal_page = get_page_by_path('portal');
        if ($portal_page && !empty($portal_page->ID)) {
            return (string) get_permalink((int) $portal_page->ID);
        }

        $candidate_pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'menu_order title',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);

        foreach ((array) $candidate_pages as $page) {
            if (empty($page->ID)) {
                continue;
            }

            if (has_shortcode((string) $page->post_content, 'myvh_portal')) {
                return (string) get_permalink((int) $page->ID);
            }
        }

        foreach (['portal', 'client-portal', 'account-portal', 'dashboard'] as $slug) {
            $candidate = get_page_by_path($slug);
            if ($candidate && !empty($candidate->ID)) {
                return (string) get_permalink((int) $candidate->ID);
            }
        }

        return $fallback;
    }

    private function is_email_verification_required_for_login(): bool {
        return (bool) myvh_setting('general.require_email_verification', true);
    }

    private function get_customer_for_user(int $user_id): ?array {
        if ($user_id <= 0) {
            return null;
        }

        global $myvh_container;

        if (!isset($myvh_container) || !is_object($myvh_container) || !method_exists($myvh_container, 'get')) {
            return null;
        }

        try {
            $customer_service = $myvh_container->get(CustomerService::class);
        } catch (Throwable $e) {
            return null;
        }

        if (!is_object($customer_service)) {
            return null;
        }

        try {
            $customer = $customer_service->get_by_user_id($user_id);
        } catch (Throwable $e) {
            return null;
        }

        return is_array($customer) ? $customer : null;
    }

    protected function redirect_after_login_success(): void {
        wp_safe_redirect($this->resolve_portal_page_url());
        exit;
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
            $error_message = wp_strip_all_tags((string) $user->get_error_message(), true);
            if ($error_message === '') {
                $error_message = __('Login failed. Please check your details and try again.', 'my-village-hall');
            }

            set_transient('myvh_login_error', $error_message, 30);
            return;
        }

        if ($this->is_email_verification_required_for_login()) {
            $customer = $this->get_customer_for_user((int) $user->ID);

            if (!empty($customer['Id']) && empty($customer['EmailVerified'])) {
                $customer_email = (string) ($customer['Email'] ?? $user->user_email ?? '');
                $customer_name = (string) ($customer['Name'] ?? $user->display_name ?? '');

                $this->get_verification_service()->send_verification_email(
                    (int) $customer['Id'],
                    (int) $user->ID,
                    $customer_email,
                    $customer_name
                );

                wp_logout();

                set_transient('myvh_login_prefill_username', $username !== '' ? $username : $customer_email, 120);
                set_transient(
                    'myvh_login_error',
                    __('Your email is not verified yet. We have sent a new verification email. Please verify your email before signing in.', 'my-village-hall'),
                    30
                );

                return;
            }
        }

        $this->redirect_after_login_success();
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
            $this->redirect_registration_error('Name is required.');
        }

        if ($email === '' || !is_email($email)) {
            $this->redirect_registration_error('A valid email address is required.');
        }

        $password_error = $this->password_validator->validate($password);
        if ($password_error !== null) {
            $this->redirect_registration_error($password_error);
        }

        if ($password !== $confirm_password) {
            $this->redirect_registration_error('Password confirmation does not match.');
        }

        if (email_exists($email)) {
            set_transient('myvh_register_existing_email', $email, 120);
            set_transient('myvh_login_prefill_username', $email, 120);
            $this->redirect_registration_error('An account with this email already exists.');
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
            $this->redirect_registration_error($user_id->get_error_message());
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
                    'email_verified' => false,
                    'user_id' => (int) $user_id,
                ]);
            } else {
                $saved = $customer_service->save([
                    'name' => $name,
                    'email' => $email,
                    'phone_number' => $phone,
                    'email_verified' => false,
                    'user_id' => (int) $user_id,
                ]);
            }

            if (is_wp_error($saved)) {
                $this->delete_user_safe((int) $user_id);
                $this->redirect_registration_error($saved->get_error_message());
            }

            EventDispatcher::dispatch(CustomerEvents::REGISTERED, [
                'customer_id' => (int) $saved,
                'user_id' => (int) $user_id,
                'email' => $email,
                'name' => $name,
            ]);
        } catch (Exception $e) {
            $this->delete_user_safe((int) $user_id);
            $this->redirect_registration_error($e->getMessage());
        }

        wp_set_current_user((int) $user_id);
        wp_set_auth_cookie((int) $user_id, true);

        delete_transient('myvh_register_prefill_name');
        delete_transient('myvh_register_prefill_email');
        delete_transient('myvh_register_prefill_phone');
        delete_transient('myvh_login_prefill_username');

        wp_safe_redirect($this->resolve_portal_page_url());
        exit;
    }

    public function handle_email_verification(): void {
        if (empty($_GET['myvh_verify_email']) || empty($_GET['uid']) || empty($_GET['token'])) {
            return;
        }

        $user_id = \intval($_GET['uid'] ?? 0);
        $token = sanitize_text_field(wp_unslash((string) ($_GET['token'] ?? '')));

        // Validate that we have a valid user ID and token
        if ($user_id <= 0 || empty($token)) {
            set_transient('myvh_verification_error', 'Invalid verification link.', 60);
            wp_safe_redirect($this->resolve_login_page_url());
            exit;
        }

        $verified = $this->get_verification_service()->verify_token($user_id, $token);

        if ($verified) {
            set_transient('myvh_verification_success', 'Email address verified. You can now sign in.', 60);
        } else {
            set_transient('myvh_verification_error', 'Invalid or expired verification link.', 60);
        }

        wp_safe_redirect($this->resolve_login_page_url());
        exit;
    }
}
