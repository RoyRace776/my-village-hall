<?php
if (!defined('ABSPATH')) exit;

class MYVH_Calendar_Ajax_Controller {

    private $booking_service;
    private $room_repository;

    public function __construct(
        MYVH_Booking_Service $booking_service,
        MYVH_Room_Repository $room_repository) {

        $this->booking_service = $booking_service;
        $this->room_repository = $room_repository;
    }

    public function register_routes() {

        add_action('wp_ajax_myvh_calendar_rooms', [$this, 'get_rooms']);
        add_action('wp_ajax_myvh_calendar_events', [$this, 'get_events']);
        add_action('wp_ajax_myvh_move_booking', [$this, 'move_booking']);
        add_action('wp_ajax_myvh_create_event', [$this, 'create_event']);
        add_action('wp_ajax_myvh_update_event', [$this, 'update_event']);

    }

    public function get_rooms() {

        check_ajax_referer('myvh_calendar', 'nonce');

        if ( ! current_user_can( 'manage_myvh' ) ) {
            wp_send_json_error( 'Permission denied', 403 );
        }

        $rooms = $this->room_repository->get_all();

        $columns = [];

        foreach ($rooms as $room) {

            $columns[] = [
                "name" => $room['Name'],
                "id"   => $room['Id']
            ];
        }

        wp_send_json($columns);
    }

    public function get_events() {

        //TODO: Fix this to make sure it returns the right data for the right context

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

        if ( ! current_user_can( 'manage_myvh' ) ) {
            wp_send_json_error( 'Permission denied', 403 );
        }

        $start = sanitize_text_field($_GET['start'] ?? null);
        $end   = sanitize_text_field($_GET['end'] ?? null);
        $context = sanitize_text_field($_GET['context'] ?? 'admin');

        $bookings = $this->booking_service->get_between($start, $end, $context);

        $events = [];

        foreach ($bookings as $b) {

            $event = [
                "id" => $b['Id'],
                "text" => $b['Description'],
                "start" => $b['StartDate'] . "T" . $b['StartTime'],
                "end"   => $b['EndDate'] . "T" . $b['EndTime'],
                "resource" => $b['RoomId']
            ];

            // 🔐 Public safety
            if ($context === 'public') {
                unset($event['id']); // optional
                $event['text'] = 'Booked';
            }

            $events[] = $event;
        }

        wp_send_json($events);
    }

    public function move_booking() {

        check_ajax_referer('myvh_calendar', 'nonce');

        if ( ! current_user_can( 'manage_myvh' ) ) {
            wp_send_json_error( 'Permission denied', 403 );
        }

        $id = intval($_POST['id']);
        $start = sanitize_text_field($_POST['start']);
        $end   = sanitize_text_field($_POST['end']);
        $room  = intval($_POST['room_id']);

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

    public function create_event() {

        check_ajax_referer('myvh_calendar', 'nonce');

        if (!current_user_can('manage_myvh')) {
            wp_send_json_error('Permission denied', 403);
        }

        $data = [
            'start'           => sanitize_text_field($_POST['start']),
            'end'             => sanitize_text_field($_POST['end']),
            'description'     => sanitize_text_field($_POST['text']),
            'room_id'         => intval($_POST['room_id'] ?? 0),
            'customer_id'     => intval($_POST['customer_id'] ?? 0),
            'organisation_id' => intval($_POST['organisation_id'] ?? 0),
        ];

        // Basic validation
        if (!$data['room_id']) {
            wp_send_json_error('Room is required');
        }

        if (!$data['customer_id']) {
            wp_send_json_error('Customer is required');
        }

        try {
            $id = $this->booking_service->save($data);

            wp_send_json_success(['id' => $id]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function update_event() {

        check_ajax_referer('myvh_calendar', 'nonce');

        if (!current_user_can('manage_myvh')) {
            wp_send_json_error('Permission denied', 403);
        }

        $data = [
            'booking_id'      => sanitize_text_field($_POST['booking_id']),
            'start'           => sanitize_text_field($_POST['start']),
            'end'             => sanitize_text_field($_POST['end']),
            'description'     => sanitize_text_field($_POST['text']),
            'room_id'         => intval($_POST['room_id'] ?? 0),
            'customer_id'     => intval($_POST['customer_id'] ?? 0),
            'organisation_id' => intval($_POST['organisation_id'] ?? 0),
        ];

        // Basic validation
        if (!$data['room_id']) {
            wp_send_json_error('Room is required');
        }

        if (!$data['customer_id']) {
            wp_send_json_error('Customer is required');
        }

        try {
            $id = $this->booking_service->save($data);

            wp_send_json_success(['id' => $id]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
}


    private function authorize_admin() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }
    }

    private function authorize_user() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Login required', 401);
        }
    }


}