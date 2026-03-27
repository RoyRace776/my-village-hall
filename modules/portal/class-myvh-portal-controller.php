<?php

class Portal_Controller {
    private $booking_service;
    private $customer_service;
    private $organisation_service;
    private $client_admin_service;
    private $invoice_generator_service;
    private $invoice_service;

    public function __construct(
        Booking_Service $booking_service,
        Customer_Service $customer_service,
        Organisation_Service $organisation_service,
        Client_Admin_Service $client_admin_service,
        Invoice_Generator_Service $invoice_generator_service
        ,
        Invoice_Service $invoice_service
    ) {
        $this->booking_service = $booking_service;
        $this->customer_service = $customer_service;
        $this->organisation_service = $organisation_service;
        $this->client_admin_service = $client_admin_service;
        $this->invoice_generator_service = $invoice_generator_service;
        $this->invoice_service = $invoice_service;
    }


    public function register(): void {
        add_action('wp_ajax_myvh_portal_get_uninvoiced_bookings', [$this, 'get_uninvoiced_bookings_for_entity']);
        add_action('wp_ajax_myvh_portal_page', [$this, 'load_page']);
        add_action('wp_ajax_myvh_portal_update_account', [$this, 'update_account']);
        add_action('wp_ajax_myvh_portal_change_password', [$this, 'change_password']);
        add_action('wp_ajax_myvh_portal_request_org_membership', [$this, 'request_organisation_membership']);
        add_action('wp_ajax_myvh_portal_approve_org_request', [$this, 'approve_organisation_membership_request']);
        add_action('wp_ajax_myvh_portal_reject_org_request', [$this, 'reject_organisation_membership_request']);
        add_action('wp_ajax_myvh_portal_save_org_billing', [$this, 'save_organisation_billing']);
        add_action('wp_ajax_myvh_portal_org_add_member', [$this, 'organisation_add_member']);
        add_action('wp_ajax_myvh_portal_org_remove_member', [$this, 'organisation_remove_member']);
        add_action('wp_ajax_myvh_portal_org_set_admin', [$this, 'organisation_set_member_admin']);
        //add_action('wp_ajax_myvh_portal_update_booking', [$this, 'update_booking']);
        //add_action('wp_ajax_myvh_portal_delete_booking', [$this, 'delete_booking']);
        add_action('wp_ajax_myvh_portal_add_client_admin', [$this, 'add_client_admin']);
        add_action('wp_ajax_myvh_portal_remove_client_admin', [$this, 'remove_client_admin']);
        add_action('wp_ajax_myvh_portal_save_customer', [$this, 'save_customer']);
        add_action('wp_ajax_myvh_portal_delete_customer', [$this, 'delete_customer']);
        add_action('wp_ajax_myvh_portal_save_client_settings', [$this, 'save_client_settings']);
        add_action('wp_ajax_myvh_portal_create_invoice', [$this, 'create_invoice']);
        //add_action('wp_ajax_myvh_portal_get_booking', [$this, 'get_booking']);
    }

    /**
     * AJAX: Get uninvoiced bookings for a customer or organisation (for drilldown)
     */
    public function get_uninvoiced_bookings_for_entity(): void {
        check_ajax_referer('myvh_portal', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        $customer_id = intval($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
        $organisation_id = intval($_GET['organisation_id'] ?? $_POST['organisation_id'] ?? 0);
        $is_client_admin = $this->current_user_is_client_admin();
        if (!$is_client_admin) {
            wp_send_json_error('Not allowed', 403);
        }

        $args = [
            'orderby' => 'b.StartDate',
            'order' => 'DESC',
        ];
        if ($customer_id) {
            $args['customer_id'] = $customer_id;
        }
        if ($organisation_id) {
            $args['organisation_id'] = $organisation_id;
        }
        if (!$customer_id && !$organisation_id) {
            wp_send_json_error('Missing customer_id or organisation_id', 400);
        }

        $bookings = $this->booking_service->get_uninvoiced_bookings($args);
        wp_send_json_success(['bookings' => $bookings]);
    }


    public function load_page(): void {

        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        check_ajax_referer('myvh_portal', 'nonce');

        $page = sanitize_text_field($_GET['page'] ?? 'dashboard');
        $customer = $this->customer_service->get_by_user_id(get_current_user_id());
        $is_client_admin = $this->current_user_is_client_admin();
        $has_customer = !empty($customer['Id']);

        switch ($page) {

            case 'bookings':
                $groups = $this->get_portal_booking_groups($customer, $is_client_admin);
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/bookings.php';
                break;

            case 'bookings-new':
                $can_create_booking = $is_client_admin || $has_customer;
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/bookings-new.php';
                break;

            case 'booking-view':
                $booking_id = intval($_GET['booking_id'] ?? 0);
                $booking = Booking_Access::get_accessible_booking(
                                $booking_id,
                                intval($customer['Id'] ?? 0),
                                $is_client_admin,
                                $this->booking_service
                            );
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/booking-view.php';
                break;

            case 'booking-edit':
                $booking_id = intval($_GET['booking_id'] ?? 0);
                $booking = Booking_Access::get_accessible_booking(
                                $booking_id,
                                intval($customer['Id'] ?? 0),
                                $is_client_admin,
                                $this->booking_service
                            );
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/booking-edit.php';
                break;

            case 'booking-delete':
                $booking_id = intval($_GET['booking_id'] ?? 0);
                $booking = Booking_Access::get_accessible_booking(
                                $booking_id,
                                intval($customer['Id'] ?? 0),
                                $is_client_admin,
                                $this->booking_service
                            );
                $delete_rules = $this->booking_service->can_delete($booking);
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/booking-delete.php';
                break;

            case 'calendar':
                $can_create_booking = $is_client_admin || $has_customer;
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/calendar.php';
                break;

                // Duplicate case 'invoices' removed

            case 'invoices':
                // Get invoice filters from request
                $selected_statuses = [];
                if (isset($_GET['statuses'])) {
                    $raw_statuses = wp_unslash($_GET['statuses']);

                    if (is_string($raw_statuses)) {
                        $selected_statuses = array_map('sanitize_text_field', explode(',', $raw_statuses));
                    } else {
                        $selected_statuses = array_map('sanitize_text_field', (array) $raw_statuses);
                    }
                }

                // Validate statuses against allowed values
                $valid_statuses = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];
                $selected_statuses = array_values(array_intersect($selected_statuses, $valid_statuses));

                // Get invoices for customer with optional status filter
                $invoices = [];
                if ($has_customer) {
                    $customer_id = intval($customer['Id']);
                    $invoices = $this->invoice_service->get_for_portal(
                        $customer_id,
                        !empty($selected_statuses) ? $selected_statuses : []
                    );
                }


                // Client admins can manually create invoices from uninvoiced bookings.
                $uninvoiced_bookings = [];
                $uninvoiced_by_customer = [];
                $uninvoiced_by_organisation = [];
                if ($is_client_admin) {
                    $uninvoiced_bookings = $this->booking_service->get_uninvoiced_bookings([
                        'orderby' => 'b.StartDate',
                        'order' => 'DESC',
                    ]);
                    $uninvoiced_by_customer = $this->booking_service->get_uninvoiced_by_customer();
                    $uninvoiced_by_organisation = $this->booking_service->get_uninvoiced_by_organisation();
                }

                // Prepare available statuses for UI filter
                $available_statuses = $valid_statuses;

                include MYVH_PLUGIN_DIR . 'modules/portal/templates/invoices.php';
                break;
            case 'account':
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/account.php';
                break;

            case 'client-admins':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $client_admins = $this->client_admin_service->get_assigned_users_for_blog(get_current_blog_id());
                $accessible_sites = $this->client_admin_service->get_accessible_sites_for_user(get_current_user_id());
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/client-admins.php';
                break;

            case 'customers':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $customers = $this->customer_service->get_all([
                    'orderby' => 'Name',
                    'order' => 'ASC',
                ]);

                include MYVH_PLUGIN_DIR . 'modules/portal/templates/customers.php';
                break;

            case 'settings':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $settings_groups = [];
                foreach (Settings_Registry::groups() as $group_key => $group_meta) {
                    $settings = Settings_Registry::get($group_key);

                    if (!$settings) {
                        continue;
                    }

                    $settings_groups[] = [
                        'key' => $group_key,
                        'label' => $group_meta['label'] ?? ucfirst((string) $group_key),
                        'schema' => $settings->schema(),
                        'values' => $settings->all(),
                    ];
                }

                include MYVH_PLUGIN_DIR . 'modules/portal/templates/settings.php';
                break;

            case 'organisations':
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
                $groups = $this->get_portal_booking_groups($customer, $is_client_admin);
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

    public function save_organisation_billing() {
        $customer = $this->get_authenticated_customer();
        if (!$customer) {
            return;
        }

        $org_id = intval($_POST['organisation_id'] ?? 0);

        if ($org_id <= 0) {
            wp_send_json_error('Organisation is required', 400);
        }

        $result = $this->organisation_service->update_billing_details_by_admin(
            $org_id,
            (int) $customer['Id'],
            [
                'invoice_organisation_bookings' => !empty($_POST['invoice_organisation_bookings']) ? 1 : 0,
                'billing_contact_name' => sanitize_text_field($_POST['billing_contact_name'] ?? ''),
                'billing_email' => sanitize_email($_POST['billing_email'] ?? ''),
                'billing_address_line1' => sanitize_text_field($_POST['billing_address_line1'] ?? ''),
                'billing_address_line2' => sanitize_text_field($_POST['billing_address_line2'] ?? ''),
                'billing_town_city' => sanitize_text_field($_POST['billing_town_city'] ?? ''),
                'billing_postcode' => sanitize_text_field($_POST['billing_postcode'] ?? ''),
                'billing_reference' => sanitize_text_field($_POST['billing_reference'] ?? ''),
            ]
        );

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success(['message' => 'Organisation billing details updated']);
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

    public function add_client_admin() {
        $this->require_client_admin_access();

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

    public function remove_client_admin() {
        $this->require_client_admin_access();

        $user_id = intval($_POST['user_id'] ?? 0);

        if ($user_id <= 0) {
            wp_send_json_error('User ID is required', 400);
        }

        $this->client_admin_service->remove_assignment(get_current_blog_id(), $user_id);

        wp_send_json_success(['message' => 'Client administrator removed']);
    }

    public function save_customer() {
        $this->require_client_admin_access();

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
        ];

        if ($customer_id > 0) {
            $payload['customer_id'] = $customer_id;
        }

        try {
            $saved = $this->customer_service->save($payload);
        } catch (Throwable $throwable) {
            wp_send_json_error($throwable->getMessage(), 400);
        }

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        wp_send_json_success([
            'message' => $customer_id > 0 ? 'Customer updated' : 'Customer created',
            'customer_id' => (int) $saved,
        ]);
    }

    public function delete_customer() {
        $this->require_client_admin_access();

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

    public function save_client_settings() {
        $this->require_client_admin_access();

        $group = sanitize_key($_POST['settings_group'] ?? '');

        if ($group === '') {
            wp_send_json_error('Settings group is required', 400);
        }

        $settings = Settings_Registry::get($group);

        if (!$settings) {
            wp_send_json_error('Settings group not found', 404);
        }

        $input = wp_unslash($_POST);
        unset($input['action'], $input['nonce'], $input['settings_group']);

        $settings->save($input);

        wp_send_json_success(['message' => 'Client settings updated']);
    }

    public function create_invoice() {
        $this->require_client_admin_access();

        // Get booking IDs from POST
        $booking_ids_raw = $_POST['booking_ids'] ?? [];
        if (is_string($booking_ids_raw)) {
            $booking_ids_raw = explode(',', $booking_ids_raw);
        }
        $booking_ids = array_map('intval', (array) $booking_ids_raw);
        $booking_ids = array_filter($booking_ids);

        if (empty($booking_ids)) {
            wp_send_json_error('No bookings selected', 400);
        }

        // Get grouping strategy
        $group_by = sanitize_key($_POST['group_by'] ?? 'per_booking');
        if (!in_array($group_by, ['per_booking', 'by_customer', 'by_organisation'], true)) {
            $group_by = 'per_booking';
        }

        // Generate invoices
        $options = [
            'group_by' => $group_by,
            'trigger_event' => 'manual'
        ];

        $result = $this->invoice_generator_service->generate_invoices_from_bookings($booking_ids, $options);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        $count = count($result);
        wp_send_json_success([
            'message' => sprintf(__('Created %d invoice(s)', 'my-village-hall'), $count),
            'invoice_ids' => $result,
            'count' => $count
        ]);
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

    private function get_portal_booking_groups($customer, bool $is_client_admin): array {
        if ($is_client_admin) {
            $result = $this->booking_service->get_booking_list();
            return $result['groups'];
        }

        if (empty($customer['Id'])) {
            return [];
        }

        $result = $this->booking_service->get_booking_list([
            'customer_id' => $customer['Id']
        ]);

        return $result['groups'];
    }

    private function current_user_is_client_admin(): bool {
        return $this->client_admin_service->can_administer_blog(get_current_user_id(), get_current_blog_id());
    }

    private function require_client_admin_access(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        check_ajax_referer('myvh_portal', 'nonce');

        if (!$this->current_user_is_client_admin()) {
            wp_send_json_error('Permission denied', 403);
        }
    }

}
