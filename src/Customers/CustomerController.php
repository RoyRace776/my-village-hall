<?php
namespace MYVH\Customers;

use MYVH\Email\PasswordSetupEmailService;

if (!defined('ABSPATH')) exit;

class CustomerController {

    private $service;
    private $request_validator;

    public function __construct(
        CustomerService $service,
        CustomerRequestValidator $request_validator
    ) {
        $this->service = $service;
        $this->request_validator = $request_validator;
    }

    public function save(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_customer');

        $data = SaveCustomerRequest::from_post(wp_unslash($_POST));

        $validation_result = $this->request_validator->validate($data);
        if (is_wp_error($validation_result)) {
            wp_redirect(admin_url('admin.php?page=myvh-customers&error=' . urlencode($validation_result->get_error_message())));
            exit;
        }

        // Check if this is a new customer and if user exists before saving
        $is_new_customer = empty($data['customer_id']);
        $existing_user = !empty($data['email']) ? get_user_by('email', $data['email']) : null;

        $result = $this->service->save($data);

        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=myvh-customers&error=' . urlencode($result->get_error_message())));
            exit;
        }

        // Send password setup email if new customer was created without existing user
        if ($is_new_customer && !$existing_user) {
            $customer_id = (int) $result;
            $customer = $this->service->get($customer_id);
            if (!empty($customer['WPUserId'])) {
                $email_service = new PasswordSetupEmailService();
                $email_service->send_password_setup_email($customer_id, (int) $customer['WPUserId']);
            }
        }

        wp_redirect(admin_url('admin.php?page=myvh-customers&updated=1'));
        exit;
    }

    public function delete(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_customer');

        $id = intval($_GET['id']);
        $this->service->delete($id);

        wp_redirect(admin_url('admin.php?page=myvh-customers&deleted=1'));
        exit;
    }
}