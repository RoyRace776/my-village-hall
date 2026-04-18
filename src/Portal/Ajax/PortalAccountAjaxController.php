<?php
namespace MYVH\Portal\Ajax;

use MYVH\Customers\CustomerService;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\PortalAuth;

class PortalAccountAjaxController {
    public function __construct(
        private CustomerService $customer_service,
        private ClientAdminService $client_admin_service
    ) {}

    public function register(): void {
        add_action('wp_ajax_myvh_portal_update_account', [$this, 'update_account']);
        add_action('wp_ajax_myvh_portal_change_password', [$this, 'change_password']);
        add_action('wp_ajax_myvh_portal_send_password_reset', [$this, 'send_password_reset_email']);
    }

    public function update_account(): void {
        PortalAuth::require_user();

        $user_id = get_current_user_id();
        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone_number = sanitize_text_field($_POST['phone_number'] ?? '');
        $address_line1 = sanitize_text_field($_POST['address_line1'] ?? '');
        $post_code = sanitize_text_field($_POST['post_code'] ?? '');

        if ($name === '') {
            wp_send_json_error('Name is required', 400);
        }

        if ($email === '' || !is_email($email)) {
            wp_send_json_error('Valid email is required', 400);
        }

        $wp_update = wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
            'user_email' => $email,
        ]);

        if (is_wp_error($wp_update)) {
            wp_send_json_error($wp_update->get_error_message(), 400);
        }

        $customer = $this->customer_service->get_by_user_id($user_id);

        if ($customer && !empty($customer['Id'])) {
            $saved = $this->customer_service->save([
                'customer_id' => (int) $customer['Id'],
                'name' => $name,
                'email' => $email,
                'phone_number' => $phone_number,
                'address_line1' => $address_line1,
                'post_code' => $post_code,
                'email_verified' => !empty($customer['EmailVerified']),
                'user_id' => $user_id,
            ]);

            if (is_wp_error($saved)) {
                wp_send_json_error($saved->get_error_message(), 400);
            }
        } elseif (!$this->current_user_is_client_admin()) {
            wp_send_json_error('Customer profile not found', 400);
        }

        wp_send_json_success([
            'message' => 'Account details updated',
            'display_name' => $name,
        ]);
    }

    public function change_password(): void {
        PortalAuth::require_user();

        $user_id = get_current_user_id();
        $current_password = (string) ($_POST['current_password'] ?? '');
        $new_password = (string) ($_POST['new_password'] ?? '');
        $confirm_password = (string) ($_POST['confirm_password'] ?? '');

        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            wp_send_json_error('All password fields are required', 400);
        }

        if ($new_password !== $confirm_password) {
            wp_send_json_error('New password and confirmation do not match', 400);
        }

        if (strlen($new_password) < 8) {
            wp_send_json_error('New password must be at least 8 characters', 400);
        }

        $user = get_userdata($user_id);

        if (!$user || !wp_check_password($current_password, $user->user_pass, $user_id)) {
            wp_send_json_error('Current password is incorrect', 400);
        }

        $updated = wp_update_user([
            'ID' => $user_id,
            'user_pass' => $new_password,
        ]);

        if (is_wp_error($updated)) {
            wp_send_json_error($updated->get_error_message(), 400);
        }

        wp_set_auth_cookie($user_id, true);
        wp_send_json_success(['message' => 'Password changed successfully']);
    }

    public function send_password_reset_email(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $customer_id = intval($_POST['customer_id'] ?? 0);

        if ($customer_id <= 0) {
            wp_send_json_error('Customer ID is required', 400);
        }

        $customer = $this->customer_service->get($customer_id);
        if (empty($customer['Id'])) {
            wp_send_json_error('Customer not found', 404);
        }

        if (empty($customer['WPUserId'])) {
            wp_send_json_error('Customer does not have a WordPress account', 400);
        }

        $email_service = new \MYVH\Email\PasswordSetupEmailService();
        $result = $email_service->send_password_setup_email($customer_id, (int) $customer['WPUserId']);

        if (!$result) {
            wp_send_json_error('Failed to send password reset email', 500);
        }

        wp_send_json_success(['message' => 'Password reset email sent successfully']);
    }

    private function current_user_is_client_admin(): bool {
        return $this->client_admin_service->can_administer_blog(get_current_user_id(), get_current_blog_id());
    }
}
