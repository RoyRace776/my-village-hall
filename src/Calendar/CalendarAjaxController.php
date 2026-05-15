<?php
namespace MYVH\Calendar;

use MYVH\Bookings\BookingService;
use MYVH\Rooms\RoomRepository;
use MYVH\Rooms\RoomColour;
use MYVH\Rooms\RoomVisibilityService;
use MYVH\Customers\CustomerService;
use MYVH\Organisations\OrganisationMemberRepository;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\AjaxResponse;
use MYVH\Portal\Support\BookingAccess;
use MYVH\Pricing\RoomRateService;

use Exception;

if (!defined('ABSPATH')) exit;

class CalendarAjaxController {

    private $calendar_service;
    private $booking_service;
    private $room_repository;
    private $customer_service;
    private $organisation_member_repo;
    private $client_admin_service;
    private $room_rate_service;
    private $room_visibility_service;

    public function __construct(
        CalendarService $calendar_service,
        BookingService $booking_service,
        RoomRepository $room_repository,
        CustomerService $customer_service,
        OrganisationMemberRepository $organisation_member_repo,
        ClientAdminService $client_admin_service,
        RoomRateService $room_rate_service,
        RoomVisibilityService $room_visibility_service) {

        $this->calendar_service = $calendar_service;
        $this->booking_service = $booking_service;
        $this->room_repository = $room_repository;
        $this->customer_service = $customer_service;
        $this->organisation_member_repo = $organisation_member_repo;
        $this->client_admin_service = $client_admin_service;
        $this->room_rate_service = $room_rate_service;
        $this->room_visibility_service = $room_visibility_service;
    }

    public function register_routes(): void {

        add_action('wp_ajax_myvh_calendar_rooms', [$this, 'get_rooms']);
        add_action('wp_ajax_myvh_get_rooms_by_venue', [$this, 'get_rooms_by_venue']);
        add_action('wp_ajax_myvh_calendar_events', [$this, 'get_events']);
        add_action('wp_ajax_myvh_move_booking', [$this, 'move_booking']);
        add_action('wp_ajax_myvh_create_event', [$this, 'create_event']);
        add_action('wp_ajax_myvh_update_event', [$this, 'update_event']);
        add_action('wp_ajax_myvh_customers', [$this, 'get_customers']);
        add_action('wp_ajax_myvh_organisations', [$this, 'get_organisations']);
        add_action('wp_ajax_myvh_calendar_get_booking', [$this, 'get_booking']);

    }

    public function get_booking(): void {

        check_ajax_referer('myvh_calendar', 'nonce');
        if (!is_user_logged_in()) {
            AjaxResponse::auth_error(__('Not logged in', 'my-village-hall'));
        }

        $booking_id = \intval($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
        if (!$booking_id) {
            AjaxResponse::error(__('Missing booking_id', 'my-village-hall'));
        }
        $customer = $this->customer_service->get_by_user_id(get_current_user_id());
        $is_client_admin = $this->client_admin_service->can_administer_blog(get_current_user_id(), get_current_blog_id());
        if ($is_client_admin) {
            $booking = $this->booking_service->get_by_id_with_details($booking_id);
        } else {
            $bookings = $this->booking_service->get_all_with_details(['customer_id' => $customer['Id']]);
            $booking = null;
            foreach ($bookings as $b) {
                if (intval($b['Id']) === $booking_id) {
                    $booking = $b;
                    break;
                }
            }
        }
        if (!$booking) {
            AjaxResponse::not_found(__('Booking not found or not accessible', 'my-village-hall'));
        }

        $can_manage_no_invoice_required = $this->current_user_can_manage_no_invoice_required();
        $booking = $this->filter_booking_for_viewer($booking, $can_manage_no_invoice_required);

        $edit_rules = $this->booking_service->can_edit($booking);
        $delete_rules = $this->booking_service->can_delete($booking);
        AjaxResponse::success([
            'booking' => $booking,
            'charges' => $this->booking_service->get_charges_for_booking($booking_id),
            'addons' => $this->booking_service->get_addons_for_booking($booking_id),
            'deposits' => $this->booking_service->get_deposit_items_for_booking($booking_id),
            'expected_deposit' => $this->booking_service->get_expected_deposit_for_booking($booking_id),
            'can_edit' => !empty($edit_rules['can_edit']),
            'edit_reason' => $edit_rules['reason'] ?? '',
            'can_delete' => !empty($delete_rules['can_delete']),
            'delete_reason' => $delete_rules['reason'] ?? '',
            'can_manage_no_invoice_required' => $can_manage_no_invoice_required,
        ]);
    }

    public function get_customers(): void {

        check_ajax_referer('myvh_calendar', 'nonce');

        if (!is_user_logged_in()) {
            AjaxResponse::auth_error(__('Not logged in', 'my-village-hall'));
        }

        $user_id = get_current_user_id();

        if ($this->client_admin_service->can_administer_blog($user_id, get_current_blog_id()) || current_user_can('manage_myvh')) {
            $customers = $this->customer_service->get_all([
                'orderby' => 'Name',
                'order' => 'ASC',
            ]);

            wp_send_json(array_map(static function ($customer): array {
                return [
                    'id' => (int) ($customer['Id'] ?? 0),
                    'name' => (string) ($customer['Name'] ?? ''),
                ];
            }, $customers ?: []));
        }

        // Map WP user → customer
        $customer = $this->customer_service->get_by_user_id($user_id);

        wp_send_json([
            [
                'id' => $customer['Id'],
                'name' => $customer['Name']
            ]
        ]);
    }

    public function get_organisations(): void {

        check_ajax_referer('myvh_calendar', 'nonce');

        if (!is_user_logged_in()) {
            AjaxResponse::auth_error(__('Not logged in', 'my-village-hall'));
        }

        $user_id = get_current_user_id();
        $can_view_any_customer_orgs =
            current_user_can('manage_myvh')
            || $this->client_admin_service->can_administer_blog($user_id, get_current_blog_id());

        if ($can_view_any_customer_orgs) {
            $customer_id = \intval($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);

            if ($customer_id > 0) {
                $orgs = $this->customer_service->get_organisations_for_customer($customer_id);
                wp_send_json($orgs ?: []);
            }
        }

        $orgs = $this->customer_service->get_organisations_for_user_id($user_id);

        wp_send_json($orgs);
    }

    public function get_rooms(): void {

        check_ajax_referer('myvh_calendar', 'nonce');

        $context = sanitize_text_field($_GET['context'] ?? $_POST['context'] ?? 'admin');
        $venue_id = absint($_GET['venue_id'] ?? $_POST['venue_id'] ?? 0);

        if ( $context === 'portal' ) {
            $this->authorize_user();
        } elseif ( ! current_user_can( 'manage_myvh' ) ) {
            AjaxResponse::permission_error(__('Permission denied', 'my-village-hall'));
        }

        wp_send_json($this->build_room_columns($this->get_bookable_rooms($venue_id)));
    }

    public function get_rooms_by_venue(): void {

        $this->verify_rooms_request_nonce();
        $this->authorize_admin();

        $venue_id = absint($_GET['venue_id'] ?? $_POST['venue_id'] ?? 0);

        AjaxResponse::success([
            'rooms' => $this->get_bookable_rooms($venue_id),
        ]);
    }

    public function get_events(): void {
        check_ajax_referer('myvh_calendar', 'nonce');

        $context = $_GET['context'] ?? 'public';

        switch ($context) {
            case 'admin':
                $this->authorize_admin();
                break;

            case 'portal':
                $this->authorize_user();
                break;

            case 'public':
            default:
                // no auth needed
                break;
        }

        $start = sanitize_text_field($_GET['start'] ?? null);
        $end   = sanitize_text_field($_GET['end'] ?? null);
        $context = sanitize_text_field($_GET['context'] ?? 'public');
        $venue_id = absint($_GET['venue_id'] ?? $_POST['venue_id'] ?? 0);

        $events = $this->calendar_service->get_events($start, $end, $context, get_current_user_id(), [
            'venue_id' => $venue_id,
        ]);

        wp_send_json($events);
    }

    public function move_booking(): void {

        check_ajax_referer('myvh_calendar', 'nonce');

        if ( ! current_user_can( 'manage_myvh' ) ) {
            AjaxResponse::permission_error(__('Permission denied', 'my-village-hall'));
        }

        $request = $this->get_request_data();

        $id = \intval($request['id'] ?? 0);
        $start = sanitize_text_field($request['start'] ?? '');
        $end   = sanitize_text_field($request['end'] ?? '');
        $room  = \intval($request['room_id'] ?? 0);

        if (!$id || !$start || !$end) {
            AjaxResponse::error(__('Missing booking movement data', 'my-village-hall'));
        }

        $result = $this->booking_service->move_booking(
            $id,
            $start,
            $end,
            $room
        );

        if (is_wp_error($result)) {
            AjaxResponse::error($result->get_error_message());
        }

        AjaxResponse::success([], __('Booking moved', 'my-village-hall'));
    }

    public function create_event(): void {

        check_ajax_referer('myvh_calendar', 'nonce');

        $request = $this->get_request_data();
        $context = sanitize_text_field($request['context'] ?? $_GET['context'] ?? $_POST['context'] ?? 'admin');

        if ($context === 'portal') {
            $this->authorize_user();
        } elseif (!current_user_can('manage_myvh')) {
            AjaxResponse::permission_error(__('Permission denied', 'my-village-hall'));
        }

        try {
            $result = $this->calendar_service->create_event($request, $context, get_current_user_id());

            if (is_wp_error($result)) {
                AjaxResponse::error($result->get_error_message());
            }

            AjaxResponse::success($result);

        } catch (Exception $e) {
            AjaxResponse::server_error($e->getMessage());
        }
    }

    public function update_event(): void {

        check_ajax_referer('myvh_calendar', 'nonce');

        $request = $this->get_request_data();
        $context = sanitize_text_field($request['context'] ?? 'admin');

        if ($context === 'portal') {
            if (!is_user_logged_in()) {
                AjaxResponse::auth_error(__('Login required', 'my-village-hall'));
            }

            $user_id = get_current_user_id();
            $is_client_admin = $this->client_admin_service->can_administer_blog($user_id, get_current_blog_id());
            $customer = $this->customer_service->get_by_user_id($user_id);
            $booking_id = \intval($request['booking_id'] ?? $request['id'] ?? 0);

            if ($booking_id <= 0) {
                AjaxResponse::error(__('Booking ID is required', 'my-village-hall'));
            }

            $booking = BookingAccess::get_accessible_booking(
                $booking_id,
                (int) ($customer['Id'] ?? 0),
                $is_client_admin,
                $this->booking_service,
                $this->organisation_member_repo
            );

            if (!$booking) {
                AjaxResponse::not_found(__('Booking not found or not accessible', 'my-village-hall'));
            }

            $edit_rules = $this->booking_service->can_edit($booking);
            if (empty($edit_rules['can_edit'])) {
                AjaxResponse::permission_error($edit_rules['reason'] ?? __('Permission denied', 'my-village-hall'));
            }
        } elseif (!current_user_can('manage_myvh')) {
            AjaxResponse::permission_error(__('Permission denied', 'my-village-hall'));
        }

        try {
            $result = $this->calendar_service->update_event($request);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message(), 400);
            }

        } catch (Exception $e) {
            $status = $e->getCode();
            AjaxResponse::error($e->getMessage(), $status >= 400 ? $status : 400);
        }

        AjaxResponse::success($result);
    }


    private function authorize_admin(): void {
        if (!current_user_can('manage_myvh')) {
            AjaxResponse::permission_error(__('Unauthorized', 'my-village-hall'));
        }
    }

    private function authorize_user(): void {
        if (!is_user_logged_in()) {
            AjaxResponse::auth_error(__('Login required', 'my-village-hall'));
        }
    }

    private function current_user_can_manage_no_invoice_required(): bool {
        if (current_user_can('manage_myvh')) {
            return true;
        }

        return $this->client_admin_service->can_administer_blog(get_current_user_id(), get_current_blog_id());
    }

    private function filter_booking_for_viewer(array $booking, bool $can_manage_no_invoice_required): array {
        if ($can_manage_no_invoice_required) {
            $booking['NoInvoiceRequired'] = \intval($booking['NoInvoiceRequired'] ?? 0);
            return $booking;
        }

        unset($booking['NoInvoiceRequired']);
        return $booking;
    }

    private function verify_rooms_request_nonce(): void {
        $valid = check_ajax_referer('myvh_calendar', 'nonce', false);

        if (!$valid) {
            $valid = check_ajax_referer('myvh_admin', 'nonce', false);
        }

        if (!$valid) {
            AjaxResponse::permission_error(__('Invalid nonce', 'my-village-hall'));
        }
    }

    private function get_bookable_rooms(int $venue_id = 0): array {
        $rooms = $this->room_repository->get_all_with_venues();

        if (empty($rooms)) {
            return [];
        }

        // Filter by visibility based on current user's permissions
        $rooms = $this->room_visibility_service->filter_rooms_for_user($rooms, get_current_user_id());

        if (empty($rooms)) {
            return [];
        }

        $room_ids = array_values(array_unique(array_filter(array_map(static function ($room): int {
            return !empty($room['Id']) ? (int) $room['Id'] : 0;
        }, $rooms))));

        if (empty($room_ids)) {
            return [];
        }

        $bookable_room_lookup = array_fill_keys(
            $this->room_rate_service->get_room_ids_with_active_rates($room_ids),
            true
        );

        if (empty($bookable_room_lookup)) {
            return [];
        }

        return array_values(array_filter($rooms, static function ($room) use ($venue_id, $bookable_room_lookup): bool {
            $room_id = !empty($room['Id']) ? (int) $room['Id'] : 0;
            $room_venue_id = !empty($room['VenueId']) ? (int) $room['VenueId'] : 0;

            if ($room_id < 1 || !isset($bookable_room_lookup[$room_id])) {
                return false;
            }

            if ($venue_id > 0 && $room_venue_id !== $venue_id) {
                return false;
            }

            return true;
        }));
    }

    private function build_room_columns(array $rooms): array {
        $columns = [];

        foreach ($rooms as $room) {
            $room_id = (int) ($room['Id'] ?? 0);
            $room_venue_id = !empty($room['VenueId']) ? (int) $room['VenueId'] : 0;

            $columns[] = [
                'name' => $room['Name'],
                'id' => $room_id,
                'venue_id' => $room_venue_id,
                'venue' => $room['VenueName'] ?? '',
                'colour' => RoomColour::resolve($room['Colour'] ?? '', $room_id),
                'roomColour' => RoomColour::resolve($room['Colour'] ?? '', $room_id),
                'allow_multiday' => !empty($room['AllowMultiDayBookings']) ? 1 : 0,
            ];
        }

        return $columns;
    }

    private function get_request_data(): array {

        $data = wp_unslash($_POST);

        if (!empty($data)) {
            return $data;
        }

        $raw = file_get_contents('php://input');

        if (!$raw) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }


}