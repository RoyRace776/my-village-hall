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

    }

    public function get_rooms() {

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

        $start = sanitize_text_field($_GET['start'] ?? null);
        $end   = sanitize_text_field($_GET['end'] ?? null);

        $bookings = $this->booking_service->get_between($start, $end);

        $events = [];

        foreach ($bookings as $b) {

            $events[] = [
                "id" => $b['Id'],
                "text" => $b['Description'],
                "start" => $b['StartDate'] . "T" . $b['StartTime'],
                "end"   => $b['EndDate'] . "T" . $b['EndTime'],
                "resource" => $b['RoomId']
            ];
        }

        wp_send_json($events);
    }

    public function move_booking() {

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
}