<?php

class MYVH_Portal_Controller {
    private $booking_service;

    public function __construct(MYVH_Booking_Service $booking_service ) {
        $this->booking_service = $booking_service;
    }

    public function register() {

        add_action('wp_ajax_myvh_portal_page', [$this, 'load_page']);
        add_action('wp_ajax_myvh_portal_update_account', [$this, 'update_account']);
        add_action('wp_ajax_myvh_portal_change_password', [$this, 'change_password']);
    }

    public function load_page() {

        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        check_ajax_referer('myvh_portal', 'nonce');

        $page = sanitize_text_field($_GET['page'] ?? 'dashboard');

        switch ($page) {

            case 'bookings':
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/bookings.php';
                break;

            case 'calendar':
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/calendar.php';
                break;

            case 'account':
                global $myvh_container;
                $customer_service = $myvh_container->get(MYVH_Customer_Service::class);
                $customer = $customer_service->get_by_user_id(get_current_user_id());
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/account.php';
                break;

            default:
                global $myvh_container;
                $customer_service = $myvh_container->get(MYVH_Customer_Service::class);
                $customer = $customer_service->get_by_user_id(get_current_user_id());
                $result = $customer ? $this->booking_service->get_booking_list([
                    'customer_id' => $customer['Id']
                ]) : ['groups' => []];
                $groups = $result['groups'];
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/dashboard.php';
        }

        wp_die();
    }

    public function update_account() {

        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        check_ajax_referer('myvh_portal', 'nonce');

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

        global $myvh_container;
        $customer_service = $myvh_container->get(MYVH_Customer_Service::class);
        $customer = $customer_service->get_by_user_id($user_id);

        if (!$customer || empty($customer['Id'])) {
            wp_send_json_error('Customer profile not found', 400);
        }

        $saved = $customer_service->save([
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

        wp_send_json_success(['message' => 'Account details updated']);
    }

    public function change_password() {

        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        check_ajax_referer('myvh_portal', 'nonce');

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
}
