<?php
namespace MYVH\Portal\Ajax;

use MYVH\Customers\CustomerService;
use MYVH\Email\PasswordSetupEmailService;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\PortalAuth;

use Throwable;

class PortalPeopleAjaxController {
    public function __construct(
        private ClientAdminService $client_admin_service,
        private CustomerService $customer_service
    ) {}

    public function register(): void {
        add_action('wp_ajax_myvh_portal_add_client_admin', [$this, 'add_client_admin']);
        add_action('wp_ajax_myvh_portal_remove_client_admin', [$this, 'remove_client_admin']);
        add_action('wp_ajax_myvh_portal_save_customer', [$this, 'save_customer']);
        add_action('wp_ajax_myvh_portal_delete_customer', [$this, 'delete_customer']);
    }

    public function add_client_admin(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $identifier = sanitize_text_field($_POST['user_identifier'] ?? '');

        if ($identifier === '') {
            wp_send_json_error('Email address or username is required', 400);
        }

        $user = $this->client_admin_service->find_user($identifier);

        if (!$user) {
            wp_send_json_error('No WordPress user was found with those details', 404);
        }

        $this->client_admin_service->add_assignment(get_current_blog_id(), (int) $user->ID);

        wp_send_json_success(['message' => 'Client administrator added']);
    }

    public function remove_client_admin(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $user_id = intval($_POST['user_id'] ?? 0);

        if ($user_id <= 0) {
            wp_send_json_error('User ID is required', 400);
        }

        $this->client_admin_service->remove_assignment(get_current_blog_id(), $user_id);

        wp_send_json_success(['message' => 'Client administrator removed']);
    }

    public function save_customer(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $customer_id = intval($_POST['customer_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone_number = sanitize_text_field($_POST['phone_number'] ?? '');
        $address_line1 = sanitize_text_field($_POST['address_line1'] ?? '');
        $post_code = sanitize_text_field($_POST['post_code'] ?? '');
        $email_verified = !empty($_POST['email_verified']);

        if ($name === '') {
            wp_send_json_error('Customer name is required', 400);
        }

        if ($email === '' || !is_email($email)) {
            wp_send_json_error('A valid customer email is required', 400);
        }

        $payload = [
            'name' => $name,
            'email' => $email,
            'phone_number' => $phone_number,
            'address_line1' => $address_line1,
            'post_code' => $post_code,
            'email_verified' => $email_verified,
            'allow_auto_confirm' => !empty($_POST['allow_auto_confirm']),
        ];

        if ($customer_id > 0) {
            $payload['customer_id'] = $customer_id;
        }

        $is_new_customer = $customer_id <= 0;
        $existing_user = !empty($email) ? get_user_by('email', $email) : null;

        try {
            $saved = $this->customer_service->save($payload);
        } catch (Throwable $throwable) {
            wp_send_json_error($throwable->getMessage(), 400);
        }

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        if ($is_new_customer && !$existing_user) {
            $saved_customer_id = (int) $saved;
            $customer = $this->customer_service->get($saved_customer_id);
            if (!empty($customer['WPUserId'])) {
                $email_service = new PasswordSetupEmailService();
                $email_service->send_password_setup_email($saved_customer_id, (int) $customer['WPUserId']);
            }
        }

        wp_send_json_success([
            'message' => $customer_id > 0 ? 'Customer updated' : 'Customer created',
            'customer_id' => (int) $saved,
        ]);
    }

    public function delete_customer(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $customer_id = intval($_POST['customer_id'] ?? 0);

        if ($customer_id <= 0) {
            wp_send_json_error('Customer ID is required', 400);
        }

        $deleted = $this->customer_service->delete($customer_id);

        if (is_wp_error($deleted)) {
            wp_send_json_error($deleted->get_error_message(), 400);
        }

        if (!$deleted) {
            wp_send_json_error('Failed to delete customer', 400);
        }

        wp_send_json_success(['message' => 'Customer deleted']);
    }
}
