<?php
namespace MYVH\Portal\Ajax;

use MYVH\Customers\CustomerService;
use MYVH\Email\PasswordSetupEmailService;
use MYVH\Login\PasswordValidator;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\AjaxResponse;
use MYVH\Portal\Support\PortalAuth;

class PortalAccountAjaxController {
    public function __construct(
        private CustomerService $customer_service,
        private ClientAdminService $client_admin_service,
        private PasswordValidator $password_validator
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
            AjaxResponse::error(__('Name is required', 'my-village-hall'));
        }

        if ($email === '' || !is_email($email)) {
            AjaxResponse::error(__('Valid email is required', 'my-village-hall'));
        }

        $wp_update = wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
            'user_email' => $email,
        ]);

        if (is_wp_error($wp_update)) {
            AjaxResponse::error($wp_update->get_error_message());
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
                AjaxResponse::error($saved->get_error_message());
            }
        } elseif (!$this->current_user_is_client_admin()) {
            AjaxResponse::error(__('Customer profile not found', 'my-village-hall'));
        }

        AjaxResponse::success([
            'display_name' => $name,
        ], __('Account details updated', 'my-village-hall'));
    }

    public function change_password(): void {
        PortalAuth::require_user();

        $user_id = get_current_user_id();
        $current_password = (string) ($_POST['current_password'] ?? '');
        $new_password = (string) ($_POST['new_password'] ?? '');
        $confirm_password = (string) ($_POST['confirm_password'] ?? '');

        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            AjaxResponse::error(__('All password fields are required', 'my-village-hall'));
        }

        if ($new_password !== $confirm_password) {
            AjaxResponse::error(__('New password and confirmation do not match', 'my-village-hall'));
        }

        $password_error = $this->password_validator->validate($new_password);
        if ($password_error !== null) {
            AjaxResponse::error($password_error);
        }

        $user = get_userdata($user_id);

        if (!$user || !wp_check_password($current_password, $user->user_pass, $user_id)) {
            AjaxResponse::error(__('Current password is incorrect', 'my-village-hall'));
        }

        $updated = wp_update_user([
            'ID' => $user_id,
            'user_pass' => $new_password,
        ]);

        if (is_wp_error($updated)) {
            AjaxResponse::error($updated->get_error_message());
        }

        wp_set_auth_cookie($user_id, true);
        AjaxResponse::success([], __('Password changed successfully', 'my-village-hall'));
    }

    public function send_password_reset_email(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $customer_id = intval($_POST['customer_id'] ?? 0);

        if ($customer_id <= 0) {
            AjaxResponse::error(__('Customer ID is required', 'my-village-hall'));
        }

        $customer = $this->customer_service->get($customer_id);
        if (empty($customer['Id'])) {
            AjaxResponse::not_found(__('Customer not found', 'my-village-hall'));
        }

        if (empty($customer['WPUserId'])) {
            AjaxResponse::error(__('Customer does not have a WordPress account', 'my-village-hall'));
        }

        $email_service = new PasswordSetupEmailService();
        $result = $email_service->send_password_setup_email($customer_id, (int) $customer['WPUserId']);

        if (!$result) {
            AjaxResponse::server_error(__('Failed to send password reset email', 'my-village-hall'));
        }

        AjaxResponse::success([], __('Password reset email sent successfully', 'my-village-hall'));
    }

    private function current_user_is_client_admin(): bool {
        return $this->client_admin_service->can_administer_blog(get_current_user_id(), get_current_blog_id());
    }
}
