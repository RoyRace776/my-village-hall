<?php

class MYVH_Portal_Controller {
    private $booking_service;
    private $customer_service;
    private $organisation_service;

    public function __construct(
        MYVH_Booking_Service $booking_service,
        MYVH_Customer_Service $customer_service,
        MYVH_Organisation_Service $organisation_service
    ) {
        $this->booking_service = $booking_service;
        $this->customer_service = $customer_service;
        $this->organisation_service = $organisation_service;
    }

    public function register() {

        add_action('wp_ajax_myvh_portal_page', [$this, 'load_page']);
        add_action('wp_ajax_myvh_portal_update_account', [$this, 'update_account']);
        add_action('wp_ajax_myvh_portal_change_password', [$this, 'change_password']);
        add_action('wp_ajax_myvh_portal_request_org_membership', [$this, 'request_organisation_membership']);
        add_action('wp_ajax_myvh_portal_approve_org_request', [$this, 'approve_organisation_membership_request']);
        add_action('wp_ajax_myvh_portal_reject_org_request', [$this, 'reject_organisation_membership_request']);
        add_action('wp_ajax_myvh_portal_org_add_member', [$this, 'organisation_add_member']);
        add_action('wp_ajax_myvh_portal_org_remove_member', [$this, 'organisation_remove_member']);
        add_action('wp_ajax_myvh_portal_org_set_admin', [$this, 'organisation_set_member_admin']);
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
                $customer = $this->customer_service->get_by_user_id(get_current_user_id());
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/account.php';
                break;

            case 'organisations':
                $customer = $this->customer_service->get_by_user_id(get_current_user_id());

                $my_memberships = [];
                $pending_requests = [];
                $manageable_organisations = [];
                $organisation_members = [];
                $organisation_pending_requests = [];
                $requestable_organisations = $this->organisation_service->get_all(true);

                if ( !empty($customer['Id']) ) {
                    $customer_id = (int) $customer['Id'];

                    $my_memberships = $this->organisation_service->get_memberships_for_customer($customer_id);
                    $pending_requests = $this->organisation_service->get_pending_requests_for_customer($customer_id);
                    $manageable_organisations = $this->organisation_service->get_manageable_organisations_for_customer($customer_id);

                    $joined_org_ids = array_map(static function($member) {
                        return (int) ($member['OrganisationId'] ?? 0);
                    }, $my_memberships);

                    $pending_org_ids = array_map(static function($request) {
                        return (int) ($request['OrganisationId'] ?? 0);
                    }, $pending_requests);

                    $blocked_org_ids = array_unique(array_merge($joined_org_ids, $pending_org_ids));

                    $requestable_organisations = array_values(array_filter(
                        $requestable_organisations,
                        static function($org) use ($blocked_org_ids) {
                            return !in_array((int) ($org['Id'] ?? 0), $blocked_org_ids, true);
                        }
                    ));

                    foreach ($manageable_organisations as $org) {
                        $org_id = (int) $org['Id'];
                        $organisation_members[$org_id] = $this->organisation_service->get_members($org_id);
                        $organisation_pending_requests[$org_id] = $this->organisation_service->get_pending_requests_for_organisation($org_id, $customer_id);
                    }
                }

                include MYVH_PLUGIN_DIR . 'modules/portal/templates/organisations.php';
                break;

            default:
                $customer = $this->customer_service->get_by_user_id(get_current_user_id());
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

        $customer = $this->customer_service->get_by_user_id($user_id);

        if (!$customer || empty($customer['Id'])) {
            wp_send_json_error('Customer profile not found', 400);
        }

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

    public function request_organisation_membership() {
        $customer = $this->get_authenticated_customer();
        if (!$customer) {
            return;
        }

        $org_id = intval($_POST['organisation_id'] ?? 0);
        $message = sanitize_text_field($_POST['message'] ?? '');

        if ($org_id <= 0) {
            wp_send_json_error('Please choose an organisation', 400);
        }

        $result = $this->organisation_service->create_membership_request($org_id, (int) $customer['Id'], $message);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success(['message' => 'Membership request sent']);
    }

    public function approve_organisation_membership_request() {
        $customer = $this->get_authenticated_customer();
        if (!$customer) {
            return;
        }

        $request_id = intval($_POST['request_id'] ?? 0);

        if ($request_id <= 0) {
            wp_send_json_error('Request ID is required', 400);
        }

        $result = $this->organisation_service->approve_membership_request($request_id, (int) $customer['Id']);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success(['message' => 'Request approved']);
    }

    public function reject_organisation_membership_request() {
        $customer = $this->get_authenticated_customer();
        if (!$customer) {
            return;
        }

        $request_id = intval($_POST['request_id'] ?? 0);

        if ($request_id <= 0) {
            wp_send_json_error('Request ID is required', 400);
        }

        $result = $this->organisation_service->reject_membership_request($request_id, (int) $customer['Id']);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success(['message' => 'Request rejected']);
    }

    public function organisation_add_member() {
        $customer = $this->get_authenticated_customer();
        if (!$customer) {
            return;
        }

        $org_id = intval($_POST['organisation_id'] ?? 0);
        $email = sanitize_email($_POST['email'] ?? '');
        $is_admin = !empty($_POST['is_admin']);

        if ($org_id <= 0) {
            wp_send_json_error('Organisation is required', 400);
        }

        if ($email === '' || !is_email($email)) {
            wp_send_json_error('Valid member email is required', 400);
        }

        $target_customer = $this->customer_service->get_by_email($email);
        if (empty($target_customer['Id'])) {
            wp_send_json_error('No customer exists with that email address', 400);
        }

        $result = $this->organisation_service->add_member_by_admin(
            $org_id,
            (int) $customer['Id'],
            (int) $target_customer['Id'],
            $is_admin
        );

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success(['message' => 'Member added']);
    }

    public function organisation_remove_member() {
        $customer = $this->get_authenticated_customer();
        if (!$customer) {
            return;
        }

        $member_id = intval($_POST['member_id'] ?? 0);

        if ($member_id <= 0) {
            wp_send_json_error('Member ID is required', 400);
        }

        $result = $this->organisation_service->remove_member_by_admin($member_id, (int) $customer['Id']);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success(['message' => 'Member removed']);
    }

    public function organisation_set_member_admin() {
        $customer = $this->get_authenticated_customer();
        if (!$customer) {
            return;
        }

        $member_id = intval($_POST['member_id'] ?? 0);
        $is_admin = !empty($_POST['is_admin']);

        if ($member_id <= 0) {
            wp_send_json_error('Member ID is required', 400);
        }

        $result = $this->organisation_service->set_member_admin_status_by_admin($member_id, (int) $customer['Id'], $is_admin);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success(['message' => 'Member status updated']);
    }

    private function get_authenticated_customer() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        check_ajax_referer('myvh_portal', 'nonce');

        $customer = $this->customer_service->get_by_user_id(get_current_user_id());
        if (empty($customer['Id'])) {
            wp_send_json_error('Customer profile not found', 400);
        }

        return $customer;
    }
}
