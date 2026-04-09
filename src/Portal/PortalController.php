<?php
namespace MYVH\Portal;

use MYVH\Addons\AddonRequestValidator;
use MYVH\Addons\AddonService;
use MYVH\Addons\SaveAddonRequest;
use MYVH\Bookings\BookingService;
use MYVH\Customers\CustomerService;
use MYVH\Organisations\OrganisationTypeService;
use MYVH\Organisations\SaveOrganisationRequest;
use MYVH\Organisations\SaveOrganisationTypeRequest;
use MYVH\Organisations\OrganisationService;
use MYVH\Portal\ClientAdminService;
use MYVH\Invoices\InvoiceGeneratorService;
use MYVH\Invoices\InvoiceService;
use MYVH\Pricing\RoomRateRequestValidator;
use MYVH\Pricing\RoomRateService;
use MYVH\Pricing\SaveRoomRateRequest;
use MYVH\Portal\Support\BookingAccess;
use MYVH\Rooms\RoomService;
use MYVH\Rooms\RoomRequestValidator;
use MYVH\Rooms\SaveRoomRequest;
use MYVH\Venues\VenueService;
use MYVH\Settings\SettingsRegistry;

use Throwable;

class PortalController {
    private $addon_service;
    private $addon_request_validator;
    private $booking_service;
    private $customer_service;
    private $organisation_service;
    private $organisation_type_service;
    private $client_admin_service;
    private $invoiceGeneratorService;
    private $invoiceService;
    private $room_service;
    private $room_request_validator;
    private $room_rate_service;
    private $room_rate_request_validator;
    private $venue_service;

    public function __construct(
        AddonService $addon_service,
        AddonRequestValidator $addon_request_validator,
        BookingService $booking_service,
        CustomerService $customer_service,
        OrganisationService $organisation_service,
        OrganisationTypeService $organisation_type_service,
        ClientAdminService $client_admin_service,
        InvoiceGeneratorService $invoiceGeneratorService,
        InvoiceService $invoiceService,
        RoomService $room_service,
        RoomRequestValidator $room_request_validator,
        RoomRateService $room_rate_service,
        RoomRateRequestValidator $room_rate_request_validator,
        VenueService $venue_service
    ) {
        $this->addon_service = $addon_service;
        $this->addon_request_validator = $addon_request_validator;
        $this->booking_service = $booking_service;
        $this->customer_service = $customer_service;
        $this->organisation_service = $organisation_service;
        $this->organisation_type_service = $organisation_type_service;
        $this->client_admin_service = $client_admin_service;
        $this->invoiceGeneratorService = $invoiceGeneratorService;
        $this->invoiceService = $invoiceService;
        $this->room_service = $room_service;
        $this->room_request_validator = $room_request_validator;
        $this->room_rate_service = $room_rate_service;
        $this->room_rate_request_validator = $room_rate_request_validator;
        $this->venue_service = $venue_service;
    }


    public function register(): void {
        add_action('wp_ajax_myvh_portal_get_uninvoiced_bookings', [$this, 'get_uninvoiced_bookings_for_entity']);
        add_action('wp_ajax_myvh_portal_page', [$this, 'load_page']);
        add_action('wp_ajax_myvh_portal_update_account', [$this, 'update_account']);
        add_action('wp_ajax_myvh_portal_change_password', [$this, 'change_password']);
        add_action('wp_ajax_myvh_portal_request_org_membership', [$this, 'request_organisation_membership']);
        add_action('wp_ajax_myvh_portal_approve_org_request', [$this, 'approve_organisation_membership_request']);
        add_action('wp_ajax_myvh_portal_reject_org_request', [$this, 'reject_organisation_membership_request']);
        add_action('wp_ajax_myvh_portal_add_organisation', [$this, 'add_organisation']);
        add_action('wp_ajax_myvh_portal_save_org_type_assignment', [$this, 'save_organisation_type_assignment']);
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
        add_action('wp_ajax_myvh_portal_send_password_reset', [$this, 'send_password_reset_email']);
        add_action('wp_ajax_myvh_portal_save_org_type', [$this, 'save_organisation_type']);
        add_action('wp_ajax_myvh_portal_delete_org_type', [$this, 'delete_organisation_type']);
        add_action('wp_ajax_myvh_portal_save_room', [$this, 'save_room']);
        add_action('wp_ajax_myvh_portal_delete_room', [$this, 'delete_room']);
        add_action('wp_ajax_myvh_portal_save_room_rate', [$this, 'save_room_rate']);
        add_action('wp_ajax_myvh_portal_delete_room_rate', [$this, 'delete_room_rate']);
        add_action('wp_ajax_myvh_portal_save_addon', [$this, 'save_addon']);
        add_action('wp_ajax_myvh_portal_delete_addon', [$this, 'delete_addon']);
        add_action('wp_ajax_myvh_portal_save_client_settings', [$this, 'save_client_settings']);
        add_action('wp_ajax_myvh_portal_create_invoice', [$this, 'create_invoice']);
        add_action('wp_ajax_myvh_portal_update_invoice_status', [$this, 'update_invoice_status']);
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
                $can_delete_booking = function(array $booking): bool {
                    $delete_rules = $this->booking_service->can_delete($booking);
                    return !empty($delete_rules['can_delete']);
                };
                include MYVH_PLUGIN_DIR . 'templates/Portal/bookings.php';
                break;

            case 'calendar':
            case 'bookings-new':
            case 'booking-view':
            case 'booking-edit':
            case 'booking-delete':
                $can_create_booking = $is_client_admin || $has_customer;
                include MYVH_PLUGIN_DIR . 'templates/Portal/calendar.php';
                break;

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
                $valid_statuses = $this->invoiceService->get_valid_statuses();
                $selected_statuses = array_values(array_intersect($selected_statuses, $valid_statuses));

                // Get invoices for customer with optional status filter.
                $invoices = [];
                if ($is_client_admin) {
                    $invoices = $this->invoiceService->get_with_customers() ?: [];

                    if (!empty($selected_statuses)) {
                        $invoices = array_values(array_filter($invoices, function ($invoice) use ($selected_statuses) {
                            return in_array($invoice['Status'] ?? '', $selected_statuses, true);
                        }));
                    }
                } elseif ($has_customer) {
                    $customer_id = intval($customer['Id']);
                    $invoices = $this->invoiceService->get_for_portal(
                        $customer_id,
                        !empty($selected_statuses) ? $selected_statuses : []
                    );
                }

                // Prepare available statuses for UI filter
                $available_statuses = $valid_statuses;

                include MYVH_PLUGIN_DIR . 'templates/Portal/invoices.php';
                break;

            case 'invoice-view':
                $invoice_id = intval($_GET['invoice_id'] ?? 0);
                $invoice = null;
                $invoice_items = [];
                $available_statuses = $this->invoiceService->get_valid_statuses();

                if ($invoice_id > 0) {
                    $customer_id = !empty($customer['Id']) ? intval($customer['Id']) : 0;
                    $invoice = $this->invoiceService->get_detail_for_portal($invoice_id, $customer_id, $is_client_admin);
                    $invoice_items = $invoice['Items'] ?? [];
                }

                include MYVH_PLUGIN_DIR . 'templates/Portal/invoice-view.php';
                break;

            case 'invoice-generate':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $uninvoiced_bookings = $this->booking_service->get_uninvoiced_bookings([
                    'orderby' => 'b.StartDate',
                    'order' => 'DESC',
                ]);
                $uninvoiced_by_customer = $this->booking_service->get_uninvoiced_by_customer();
                $uninvoiced_by_organisation = $this->booking_service->get_uninvoiced_by_organisation();

                include MYVH_PLUGIN_DIR . 'templates/Portal/invoice-generate.php';
                break;
            case 'account':
                include MYVH_PLUGIN_DIR . 'templates/Portal/account.php';
                break;

            case 'client-admins':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $client_admins = $this->client_admin_service->get_assigned_users_for_blog(get_current_blog_id());
                $accessible_sites = $this->client_admin_service->get_accessible_sites_for_user(get_current_user_id());
                include MYVH_PLUGIN_DIR . 'templates/Portal/client-admins.php';
                break;

            case 'customers':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $customers = $this->customer_service->get_all([
                    'orderby' => 'Name',
                    'order' => 'ASC',
                ]);

                include MYVH_PLUGIN_DIR . 'templates/Portal/customers.php';
                break;

            case 'customer-edit':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $customer_id = intval($_GET['id'] ?? 0);

                if (!$customer_id) {
                    wp_send_json_error('Invalid customer ID', 400);
                }

                $customer = $this->customer_service->get($customer_id);

                include MYVH_PLUGIN_DIR . 'templates/Portal/customer-edit.php';
                break;

            case 'customer-add':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                include MYVH_PLUGIN_DIR . 'templates/Portal/customer-add.php';
                break;

            case 'organisation-add':
                if (empty($customer['Id'])) {
                    wp_send_json_error('Customer profile not found', 400);
                }

                $organisation_types = $is_client_admin ? $this->organisation_type_service->get_all() : [];
                $default_organisation_type_id = $this->get_default_organisation_type_id($organisation_types);

                include MYVH_PLUGIN_DIR . 'templates/Portal/organisation-add.php';
                break;

            case 'rooms':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $rooms = $this->room_service->get_all_with_venues();
                include MYVH_PLUGIN_DIR . 'templates/Portal/rooms.php';
                break;

            case 'room-add':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $venues = $this->venue_service->get_all();
                include MYVH_PLUGIN_DIR . 'templates/Portal/room-add.php';
                break;

            case 'room-edit':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $room_id = intval($_GET['id'] ?? 0);

                if (!$room_id) {
                    wp_send_json_error('Invalid room ID', 400);
                }

                $room = $this->room_service->get($room_id);
                $venues = $this->venue_service->get_all();
                include MYVH_PLUGIN_DIR . 'templates/Portal/room-edit.php';
                break;

            case 'room-rates':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $rates = $this->room_rate_service->get_all();
                $rooms = $this->room_service->get_all_with_venues();
                $organisation_types = $this->organisation_type_service->get_all();
                include MYVH_PLUGIN_DIR . 'templates/Portal/room-rates.php';
                break;

            case 'addons':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $addons = $this->addon_service->get_with_relations();
                include MYVH_PLUGIN_DIR . 'templates/Portal/addons.php';
                break;

            case 'addon-add':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $rooms = $this->room_service->get_all_with_venues();
                include MYVH_PLUGIN_DIR . 'templates/Portal/addon-add.php';
                break;

            case 'addon-edit':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $addon_id = intval($_GET['id'] ?? 0);

                if (!$addon_id) {
                    wp_send_json_error('Invalid add-on ID', 400);
                }

                $addon = $this->addon_service->get($addon_id);
                if (!$addon) {
                    wp_send_json_error('Add-on not found', 404);
                }

                $rooms = $this->room_service->get_all_with_venues();
                include MYVH_PLUGIN_DIR . 'templates/Portal/addon-edit.php';
                break;

            case 'room-rate-add':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $rooms = $this->room_service->get_all_with_venues();
                $organisation_types = $this->organisation_type_service->get_all();
                $selected_room_id = intval($_GET['room_id'] ?? 0);
                include MYVH_PLUGIN_DIR . 'templates/Portal/room-rate-add.php';
                break;

            case 'room-rate-edit':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $rate_id = intval($_GET['id'] ?? 0);

                if (!$rate_id) {
                    wp_send_json_error('Invalid rate ID', 400);
                }

                $rate = $this->room_rate_service->get($rate_id);
                if (!$rate) {
                    wp_send_json_error('Room rate not found', 404);
                }

                $rooms = $this->room_service->get_all_with_venues();
                $organisation_types = $this->organisation_type_service->get_all();
                include MYVH_PLUGIN_DIR . 'templates/Portal/room-rate-edit.php';
                break;

            case 'organisation-types':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $organisation_types = $this->organisation_type_service->get_all();
                include MYVH_PLUGIN_DIR . 'templates/Portal/organisation-types.php';
                break;

            case 'organisation-type-add':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                include MYVH_PLUGIN_DIR . 'templates/Portal/organisation-type-add.php';
                break;

            case 'organisation-type-edit':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $org_type_id = intval($_GET['id'] ?? 0);
                if (!$org_type_id) {
                    wp_send_json_error('Invalid organisation type ID', 400);
                }

                $organisation_type = $this->organisation_type_service->get($org_type_id);
                if (!$organisation_type) {
                    wp_send_json_error('Organisation type not found', 404);
                }

                include MYVH_PLUGIN_DIR . 'templates/Portal/organisation-type-edit.php';
                break;

            case 'settings':
                if (!$is_client_admin) {
                    wp_send_json_error('Permission denied', 403);
                }

                $settings_groups = [];
                $current_user_id = get_current_user_id();

                foreach (SettingsRegistry::groups() as $group_key => $group_meta) {
                    $settings = SettingsRegistry::get($group_key);

                    if (!$settings) {
                        continue;
                    }

                    if (!$settings->is_visible_to_client_admin($current_user_id)) {
                        continue;
                    }

                    $settings_groups[] = [
                        'key' => $group_key,
                        'label' => $group_meta['label'] ?? ucfirst((string) $group_key),
                        'schema' => $settings->schema(),
                        'values' => $settings->all(),
                    ];
                }

                include MYVH_PLUGIN_DIR . 'templates/Portal/settings.php';
                break;

            case 'organisations':
                $my_memberships = [];
                $pending_requests = [];
                $manageable_organisations = [];
                $organisation_members = [];
                $organisation_pending_requests = [];
                $organisation_types = $is_client_admin ? $this->organisation_type_service->get_all() : [];
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

                include MYVH_PLUGIN_DIR . 'templates/Portal/organisations.php';
                break;

            default:
                $groups = $this->get_portal_booking_groups($customer, $is_client_admin);
                $can_delete_booking = function(array $booking): bool {
                    $delete_rules = $this->booking_service->can_delete($booking);
                    return !empty($delete_rules['can_delete']);
                };
                include MYVH_PLUGIN_DIR . 'templates/Portal/dashboard.php';
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

    public function add_organisation() {
        $customer = $this->get_authenticated_customer();
        if (!$customer) {
            return;
        }

        $allow_type_changes = $this->current_user_is_client_admin();
        $payload = SaveOrganisationRequest::from_post(wp_unslash($_POST), false);
        if ($allow_type_changes) {
            $payload['organisation_type_id'] = intval($_POST['organisation_type_id'] ?? 0);
        }
        $payload['is_active'] = 1;

        $saved = $this->organisation_service->save($payload, $allow_type_changes);

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        $organisation_id = (int) $saved;

        if ($organisation_id <= 0) {
            wp_send_json_error('Organisation save failed', 400);
        }

        $membership = $this->organisation_service->add_member($organisation_id, (int) $customer['Id'], true);

        if (is_wp_error($membership) || !$membership) {
            $this->organisation_service->delete($organisation_id);
            $message = is_wp_error($membership)
                ? $membership->get_error_message()
                : 'Organisation membership assignment failed';
            wp_send_json_error($message, 400);
        }

        wp_send_json_success([
            'message' => 'Organisation created',
            'organisation_id' => $organisation_id,
        ]);
    }

    public function save_organisation_type_assignment() {
        $this->require_client_admin_access();

        $organisation_id = intval($_POST['organisation_id'] ?? 0);
        if ($organisation_id <= 0) {
            wp_send_json_error('Organisation is required', 400);
        }

        $existing = $this->organisation_service->get_by_id($organisation_id);
        if (empty($existing['Id'])) {
            wp_send_json_error('Organisation not found', 404);
        }

        $payload = [
            'organisation_id' => $organisation_id,
            'name' => $existing['Name'] ?? '',
            'contact_email' => $existing['ContactEmail'] ?? '',
            'contact_phone' => $existing['ContactPhone'] ?? '',
            'website_url' => $existing['WebsiteUrl'] ?? null,
            'organisation_type_id' => intval($_POST['organisation_type_id'] ?? 0),
            'invoice_organisation_bookings' => !empty($existing['InvoiceOrganisationBookings']) ? 1 : 0,
            'billing_contact_name' => $existing['BillingContactName'] ?? '',
            'billing_email' => $existing['BillingEmail'] ?? '',
            'billing_address_line1' => $existing['BillingAddressLine1'] ?? '',
            'billing_address_line2' => $existing['BillingAddressLine2'] ?? '',
            'billing_town_city' => $existing['BillingTownCity'] ?? '',
            'billing_postcode' => $existing['BillingPostcode'] ?? '',
            'billing_reference' => $existing['BillingReference'] ?? '',
            'is_active' => !empty($existing['IsActive']) ? 1 : 0,
            'is_default' => !empty($existing['IsDefault']) ? 1 : 0,
            'default_public' => !empty($existing['DefaultPublic']) ? 1 : 0,
        ];

        $saved = $this->organisation_service->save($payload, true);

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        if (!$saved) {
            wp_send_json_error('Organisation update failed', 400);
        }

        wp_send_json_success(['message' => 'Organisation type updated']);
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
            'allow_auto_confirm' => !empty($_POST['allow_auto_confirm']),
        ];

        if ($customer_id > 0) {
            $payload['customer_id'] = $customer_id;
        }

        // Check if this is a new customer and if user exists before saving
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

        // Send password setup email if new customer was created without existing user
        if ($is_new_customer && !$existing_user) {
            $saved_customer_id = (int) $saved;
            $customer = $this->customer_service->get($saved_customer_id);
            if (!empty($customer['WPUserId'])) {
                $email_service = new \MYVH\Email\PasswordSetupEmailService();
                $email_service->send_password_setup_email($saved_customer_id, (int) $customer['WPUserId']);
            }
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

    public function send_password_reset_email() {
        $this->require_client_admin_access();

        $customer_id = intval($_POST['customer_id'] ?? 0);

        if ($customer_id <= 0) {
            wp_send_json_error('Customer ID is required', 400);
        }

        // Get customer and verify it belongs to this organization
        $customer = $this->customer_service->get($customer_id);
        if (empty($customer['Id'])) {
            wp_send_json_error('Customer not found', 404);
        }

        // Check if customer has a WordPress user
        if (empty($customer['WPUserId'])) {
            wp_send_json_error('Customer does not have a WordPress account', 400);
        }

        // Send password setup email
        $email_service = new \MYVH\Email\PasswordSetupEmailService();
        $result = $email_service->send_password_setup_email($customer_id, (int) $customer['WPUserId']);

        if ($result) {
            wp_send_json_success(['message' => 'Password reset email sent successfully']);
        } else {
            wp_send_json_error('Failed to send password reset email', 500);
        }
    }

    public function save_organisation_type() {
        $this->require_client_admin_access();

        $payload = SaveOrganisationTypeRequest::from_post(wp_unslash($_POST));

        try {
            $saved = $this->organisation_type_service->save($payload);
        } catch (Throwable $throwable) {
            wp_send_json_error($throwable->getMessage(), 400);
        }

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        if (!$saved) {
            wp_send_json_error('Organisation type save failed', 400);
        }

        wp_send_json_success([
            'message' => !empty($payload['org_type_id']) ? 'Organisation type updated' : 'Organisation type created',
            'org_type_id' => !empty($payload['org_type_id']) ? (int) $payload['org_type_id'] : (int) $saved,
        ]);
    }

    public function delete_organisation_type() {
        $this->require_client_admin_access();

        $org_type_id = intval($_POST['org_type_id'] ?? 0);

        if ($org_type_id <= 0) {
            wp_send_json_error('Organisation type ID is required', 400);
        }

        $deleted = $this->organisation_type_service->delete($org_type_id);

        if (is_wp_error($deleted)) {
            wp_send_json_error($deleted->get_error_message(), 400);
        }

        if (!$deleted) {
            wp_send_json_error('Failed to delete organisation type', 400);
        }

        wp_send_json_success(['message' => 'Organisation type deleted']);
    }

    public function save_room() {
        $this->require_client_admin_access();

        $payload = SaveRoomRequest::from_post(wp_unslash($_POST));
        $validation = $this->room_request_validator->validate($payload);

        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message(), 400);
        }

        try {
            $saved = $this->room_service->save($payload);
        } catch (Throwable $throwable) {
            wp_send_json_error($throwable->getMessage(), 400);
        }

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        if (!$saved) {
            wp_send_json_error('Room save failed', 400);
        }

        wp_send_json_success([
            'message' => !empty($payload['room_id']) ? 'Room updated' : 'Room created',
            'room_id' => !empty($payload['room_id']) ? (int) $payload['room_id'] : (int) $saved,
            'redirect' => !empty($payload['room_id'])
                ? 'rooms'
                : ('room-rate-add?room_id=' . (int) $saved),
        ]);
    }

    public function delete_room() {
        $this->require_client_admin_access();

        $room_id = intval($_POST['room_id'] ?? 0);

        if ($room_id <= 0) {
            wp_send_json_error('Room ID is required', 400);
        }

        $deleted = $this->room_service->delete($room_id);

        if (is_wp_error($deleted)) {
            wp_send_json_error($deleted->get_error_message(), 400);
        }

        if (!$deleted) {
            wp_send_json_error('Failed to delete room', 400);
        }

        wp_send_json_success(['message' => 'Room deleted']);
    }

    public function save_room_rate() {
        $this->require_client_admin_access();

        $payload = SaveRoomRateRequest::from_post(wp_unslash($_POST));
        $validation = $this->room_rate_request_validator->validate($payload);

        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message(), 400);
        }

        try {
            $saved = $this->room_rate_service->save($payload);
        } catch (Throwable $throwable) {
            wp_send_json_error($throwable->getMessage(), 400);
        }

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        if (!$saved) {
            wp_send_json_error('Room rate save failed', 400);
        }

        wp_send_json_success([
            'message' => !empty($payload['rate_id']) ? 'Room rate updated' : 'Room rate created',
            'rate_id' => !empty($payload['rate_id']) ? (int) $payload['rate_id'] : (int) $saved,
            'redirect' => !empty($payload['rate_id']) ? 'room-rates' : 'room-rates',
        ]);
    }

    public function delete_room_rate() {
        $this->require_client_admin_access();

        $rate_id = intval($_POST['rate_id'] ?? 0);

        if ($rate_id <= 0) {
            wp_send_json_error('Room rate ID is required', 400);
        }

        $deleted = $this->room_rate_service->delete($rate_id);

        // if (is_wp_error($deleted)) {
        //     wp_send_json_error($deleted->get_error_message(), 400);
        // }

        if (!$deleted) {
            wp_send_json_error('Failed to delete room rate', 400);
        }

        wp_send_json_success(['message' => 'Room rate deleted']);
    }

    public function save_addon() {
        $this->require_client_admin_access();

        $payload = SaveAddonRequest::from_post(wp_unslash($_POST));
        $validation = $this->addon_request_validator->validate($payload);

        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message(), 400);
        }

        try {
            $saved = $this->addon_service->save($payload);
        } catch (Throwable $throwable) {
            wp_send_json_error($throwable->getMessage(), 400);
        }

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        if (!$saved) {
            wp_send_json_error('Add-on save failed', 400);
        }

        wp_send_json_success([
            'message' => !empty($payload['addon_id']) ? 'Add-on updated' : 'Add-on created',
            'addon_id' => !empty($payload['addon_id']) ? (int) $payload['addon_id'] : (int) $saved,
            'redirect' => 'addons',
        ]);
    }

    public function delete_addon() {
        $this->require_client_admin_access();

        $addon_id = intval($_POST['addon_id'] ?? 0);

        if ($addon_id <= 0) {
            wp_send_json_error('Add-on ID is required', 400);
        }

        $deleted = $this->addon_service->delete($addon_id);

        // if (is_wp_error($deleted)) {
        //     wp_send_json_error($deleted->get_error_message(), 400);
        // }

        if (!$deleted) {
            wp_send_json_error('Failed to archive add-on', 400);
        }

        wp_send_json_success(['message' => 'Add-on archived']);
    }

    public function save_client_settings() {
        $this->require_client_admin_access();

        $group = sanitize_key($_POST['settings_group'] ?? '');

        if ($group === '') {
            wp_send_json_error('Settings group is required', 400);
        }

        $settings = SettingsRegistry::get($group);

        if (!$settings) {
            wp_send_json_error('Settings group not found', 404);
        }

        $current_user_id = get_current_user_id();

        if (!$settings->is_visible_to_client_admin($current_user_id) || !$settings->user_can_access($current_user_id)) {
            wp_send_json_error('Permission denied for this settings group', 403);
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

        $result = $this->invoiceGeneratorService->generate_invoices_from_bookings($booking_ids, $options);

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

    public function update_invoice_status(): void {
        $this->require_client_admin_access();

        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');

        if ($invoice_id <= 0) {
            wp_send_json_error(['message' => 'Invalid invoice'], 400);
        }

        if (!$this->invoiceService->get($invoice_id)) {
            wp_send_json_error(['message' => 'Invoice not found'], 404);
        }

        $result = $this->invoiceService->update_status($invoice_id, $status);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }

        wp_send_json_success([
            'message' => 'Invoice status updated.',
            'status' => $status,
            'status_label' => ucfirst($status),
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

    private function get_default_organisation_type_id(array $organisation_types): int {
        foreach ($organisation_types as $organisation_type) {
            if (!empty($organisation_type['IsDefault'])) {
                return (int) ($organisation_type['Id'] ?? 0);
            }
        }

        return 0;
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
