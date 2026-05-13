<?php
namespace MYVH\Portal\Ajax;

use MYVH\Customers\CustomerService;
use MYVH\Email\PasswordSetupEmailService;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\PortalAuth;
use MYVH\Portal\Support\AjaxResponse;

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
            AjaxResponse::error(__('Email address or username is required', 'my-village-hall'));
        }

        $user = $this->client_admin_service->find_user($identifier);

        if (!$user) {
            AjaxResponse::not_found(__('No WordPress user was found with those details', 'my-village-hall'));
        }

        $this->client_admin_service->add_assignment(get_current_blog_id(), (int) $user->ID);

        AjaxResponse::success([], __('Client administrator added', 'my-village-hall'));
    }

    public function remove_client_admin(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $user_id = intval($_POST['user_id'] ?? 0);

        if ($user_id <= 0) {
            AjaxResponse::error(__('User ID is required', 'my-village-hall'));
        }

        $this->client_admin_service->remove_assignment(get_current_blog_id(), $user_id);

        AjaxResponse::success([], __('Client administrator removed', 'my-village-hall'));
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
            AjaxResponse::validation_error(['name' => __('Customer name is required', 'my-village-hall')]);
        }

        if ($email === '' || !is_email($email)) {
            AjaxResponse::validation_error(['email' => __('A valid customer email is required', 'my-village-hall')]);
        }

        $payload = [
            'name' => $name,
            'email' => $email,
            'phone_number' => $phone_number,
            'address_line1' => $address_line1,
            'post_code' => $post_code,
            'email_verified' => $email_verified,
            'allow_auto_confirm' => !empty($_POST['allow_auto_confirm']),
            'single_booking_auto_invoice_rule_id' => intval($_POST['single_booking_auto_invoice_rule_id'] ?? 0),
            'recurring_booking_auto_invoice_rule_id' => intval($_POST['recurring_booking_auto_invoice_rule_id'] ?? 0),
        ];

        if ($customer_id > 0) {
            $payload['customer_id'] = $customer_id;
        }

        $is_new_customer = $customer_id <= 0;
        $existing_user = !empty($email) ? get_user_by('email', $email) : null;

        try {
            $saved = $this->customer_service->save($payload);
        } catch (Throwable $throwable) {
            AjaxResponse::server_error($throwable->getMessage());
        }

        if (is_wp_error($saved)) {
            AjaxResponse::error($saved->get_error_message());
        }

        if ($is_new_customer && !$existing_user) {
            $saved_customer_id = (int) $saved;
            $customer = $this->customer_service->get($saved_customer_id);
            if (!empty($customer['WPUserId'])) {
                $email_service = new PasswordSetupEmailService();
                $email_service->send_password_setup_email($saved_customer_id, (int) $customer['WPUserId']);
            }
        }

        $message = $customer_id > 0 ? __('Customer updated', 'my-village-hall') : __('Customer created', 'my-village-hall');
        AjaxResponse::success([
            'customer_id' => (int) $saved,
        ], $message);
    }

    public function delete_customer(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $customer_id = intval($_POST['customer_id'] ?? 0);

        if ($customer_id <= 0) {
            AjaxResponse::error(__('Customer ID is required', 'my-village-hall'));
        }

        $deleted = $this->customer_service->delete($customer_id);

        if (is_wp_error($deleted)) {
            AjaxResponse::error($deleted->get_error_message());
        }

        if (!$deleted) {
            AjaxResponse::error(__('Failed to delete customer', 'my-village-hall'));
        }

        AjaxResponse::success([], __('Customer deleted', 'my-village-hall'));
    }
}
