<?php
namespace MYVH\Calendar;

use MYVH\Bookings\BookingService;
use MYVH\Rooms\RoomRepository;
use MYVH\Rooms\RoomColour;
use MYVH\Customers\CustomerService;
use MYVH\Portal\ClientAdminService;

use Exception;

if (!defined('ABSPATH')) exit;

class CalendarAjaxController {

    private $calendar_service;
    private $booking_service;
    private $room_repository;
    private $customer_service;
    private $client_admin_service;

    public function __construct(
        CalendarService $calendar_service,
        BookingService $booking_service,
        RoomRepository $room_repository,
        CustomerService $customer_service,
        ClientAdminService $client_admin_service) {

        $this->calendar_service = $calendar_service;
        $this->booking_service = $booking_service;
        $this->room_repository = $room_repository;
        $this->customer_service = $customer_service;
        $this->client_admin_service = $client_admin_service;
    }

    public function register_routes(): void {

        add_action('wp_ajax_myvh_calendar_rooms', [$this, 'get_rooms']);
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
            wp_send_json_error('Not logged in', 401);
        }

        $booking_id = intval($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
        if (!$booking_id) {
            wp_send_json_error('Missing booking_id', 400);
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
            wp_send_json_error('Booking not found or not accessible', 404);
        }
        $edit_rules = $this->booking_service->can_edit($booking);
        $delete_rules = $this->booking_service->can_delete($booking);
        wp_send_json_success([
            'booking' => $booking,
            'addons' => $this->booking_service->get_addons_for_booking($booking_id),
            'can_edit' => !empty($edit_rules['can_edit']),
            'edit_reason' => $edit_rules['reason'] ?? '',
            'can_delete' => !empty($delete_rules['can_delete']),
            'delete_reason' => $delete_rules['reason'] ?? '',
        ]);
    }

    public function get_customers(): void {

        check_ajax_referer('myvh_calendar', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
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
            wp_send_json_error('Not logged in', 401);
        }

        $user_id = get_current_user_id();
        $can_view_any_customer_orgs =
            current_user_can('manage_myvh')
            || $this->client_admin_service->can_administer_blog($user_id, get_current_blog_id());

        if ($can_view_any_customer_orgs) {
            $customer_id = intval($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);

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
            wp_send_json_error( 'Permission denied', 403 );
        }

        $rooms = $this->room_repository->get_all_with_venues();

        $columns = [];

        foreach ($rooms as $room) {

            $room_venue_id = !empty($room['VenueId']) ? (int) $room['VenueId'] : 0;

            if ($venue_id > 0 && $room_venue_id !== $venue_id) {
                continue;
            }

            $columns[] = [
                "name" => $room['Name'],
                "id"   => $room['Id'],
                "venue_id" => $room_venue_id,
                "venue" => $room['VenueName'] ?? '',
                "colour" => RoomColour::resolve($room['Colour'] ?? '', (int) ($room['Id'] ?? 0)),
                "roomColour" => RoomColour::resolve($room['Colour'] ?? '', (int) ($room['Id'] ?? 0)),
                "allow_multiday" => !empty($room['AllowMultiDayBookings']) ? 1 : 0
            ];
        }

        wp_send_json($columns);
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
            wp_send_json_error( 'Permission denied', 403 );
        }

        $request = $this->get_request_data();

        $id = intval($request['id'] ?? 0);
        $start = sanitize_text_field($request['start'] ?? '');
        $end   = sanitize_text_field($request['end'] ?? '');
        $room  = intval($request['room_id'] ?? 0);

        if (!$id || !$start || !$end) {
            wp_send_json_error('Missing booking movement data', 400);
        }

        $result = $this->booking_service->move_booking(
            $id,
            $start,
            $end,
            $room
        );

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success();
    }

    public function create_event(): void {

        check_ajax_referer('myvh_calendar', 'nonce');

        $request = $this->get_request_data();
        $context = sanitize_text_field($request['context'] ?? $_GET['context'] ?? $_POST['context'] ?? 'admin');

        if ($context === 'portal') {
            $this->authorize_user();
        } elseif (!current_user_can('manage_myvh')) {
            wp_send_json_error('Permission denied', 403);
        }

        try {
            $result = $this->calendar_service->create_event($request, $context, get_current_user_id());

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function update_event(): void {

        check_ajax_referer('myvh_calendar', 'nonce');

        $request = $this->get_request_data();
        $context = sanitize_text_field($request['context'] ?? 'admin');

        if ($context === 'portal') {
            if (!is_user_logged_in()) {
                wp_send_json_error('Login required', 401);
            }
            if (!$this->client_admin_service->can_administer_blog(get_current_user_id(), get_current_blog_id())) {
                wp_send_json_error('Permission denied', 403);
            }
        } elseif (!current_user_can('manage_myvh')) {
            wp_send_json_error('Permission denied', 403);
        }

        try {
            $result = $this->calendar_service->update_event($request);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
}


    private function authorize_admin(): void {
        if (!current_user_can('manage_myvh')) {
            wp_send_json_error('Unauthorized', 403);
        }
    }

    private function authorize_user(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error('Login required', 401);
        }
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