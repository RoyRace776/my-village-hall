<?php
if (!defined('ABSPATH')) exit;

class MYVH_Calendar_Ajax_Controller {

    private $calendar_service;
    private $booking_service;
    private $room_repository;
    private $customer_service;

    public function __construct(
        MYVH_Calendar_Service $calendar_service,
        MYVH_Booking_Service $booking_service,
        MYVH_Room_Repository $room_repository,
        MYVH_Customer_Service $customer_service) {

        $this->calendar_service = $calendar_service;
        $this->booking_service = $booking_service;
        $this->room_repository = $room_repository;
        $this->customer_service = $customer_service;
    }

    public function register_routes(): void {

        add_action('wp_ajax_myvh_calendar_rooms', [$this, 'get_rooms']);
        add_action('wp_ajax_myvh_calendar_events', [$this, 'get_events']);
        add_action('wp_ajax_myvh_move_booking', [$this, 'move_booking']);
        add_action('wp_ajax_myvh_create_event', [$this, 'create_event']);
        add_action('wp_ajax_myvh_update_event', [$this, 'update_event']);
        add_action('wp_ajax_myvh_customers', [$this, 'get_customers']);
        add_action('wp_ajax_myvh_organisations', [$this, 'get_organisations']);

    }

    public function get_customers(): void {

        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        $user_id = get_current_user_id();

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

        if (current_user_can('manage_myvh')) {
            $customer_id = intval($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);

            if ($customer_id > 0) {
                $orgs = $this->customer_service->get_organisations_for_customer($customer_id);
                wp_send_json($orgs ?: []);
            }
        }

        $orgs = $this->customer_service->get_organisations_for_user_id(get_current_user_id());

        wp_send_json($orgs);
    }

    public function get_rooms(): void {

        check_ajax_referer('myvh_calendar', 'nonce');

        $context = sanitize_text_field($_GET['context'] ?? $_POST['context'] ?? 'admin');

        if ( $context === 'portal' ) {
            $this->authorize_user();
        } elseif ( ! current_user_can( 'manage_myvh' ) ) {
            wp_send_json_error( 'Permission denied', 403 );
        }

        $rooms = $this->room_repository->get_all_with_venues();

        $columns = [];

        foreach ($rooms as $room) {

            $columns[] = [
                "name" => $room['Name'],
                "id"   => $room['Id'],
                "venue_id" => !empty($room['VenueId']) ? (int) $room['VenueId'] : 0,
                "venue" => $room['VenueName'] ?? '',
                "allow_multiday" => !empty($room['AllowMultiDayBookings']) ? 1 : 0
            ];
        }

        wp_send_json($columns);
    }

    public function get_events(): void {
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

        check_ajax_referer('myvh_calendar', 'nonce');

        $start = sanitize_text_field($_GET['start'] ?? null);
        $end   = sanitize_text_field($_GET['end'] ?? null);
        $context = sanitize_text_field($_GET['context'] ?? 'public');

        $events = $this->calendar_service->get_events($start, $end, $context, get_current_user_id());

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

        if (!current_user_can('manage_myvh')) {
            wp_send_json_error('Permission denied', 403);
        }

        $request = $this->get_request_data();
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