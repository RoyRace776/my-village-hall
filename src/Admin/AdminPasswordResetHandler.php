<?php
namespace MYVH\Admin;

use MYVH\Customers\CustomerService;
use MYVH\Email\PasswordSetupEmailService;

if (!defined('ABSPATH')) exit;

class AdminPasswordResetHandler {
    private CustomerService $customer_service;

    public function __construct(CustomerService $customer_service) {
        $this->customer_service = $customer_service;
    }

    public function register(): void {
        add_action('wp_ajax_myvh_admin_send_password_reset', [$this, 'send_password_reset_email']);
    }

    public function send_password_reset_email(): void {
        // Check permissions
        if (!current_user_can('manage_myvh')) {
            wp_send_json_error('Permission denied', 403);
        }

        // Verify nonce
        check_ajax_referer('myvh_admin');

        // Get and validate customer ID
        $customer_id = \intval($_POST['customer_id'] ?? 0);
        if ($customer_id <= 0) {
            wp_send_json_error('Invalid customer ID', 400);
        }

        // Get customer
        $customer = $this->customer_service->get($customer_id);
        if (empty($customer['Id'])) {
            wp_send_json_error('Customer not found', 404);
        }

        // Check if customer has a WordPress user
        if (empty($customer['WPUserId'])) {
            wp_send_json_error('Customer does not have a WordPress account', 400);
        }

        // Send password setup email
        $email_service = new PasswordSetupEmailService();
        $result = $email_service->send_password_setup_email($customer_id, (int) $customer['WPUserId']);

        if ($result) {
            wp_send_json_success(['message' => 'Password reset email sent successfully']);
        } else {
            wp_send_json_error('Failed to send password reset email', 500);
        }
    }
}
