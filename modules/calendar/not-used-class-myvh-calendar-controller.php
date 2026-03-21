<?php

class MYVH_Calendar_Controller {

    private $calendar_service;
    private $booking_service;

    public function __construct(MYVH_Calendar_Service $calendar_service,
                                MYVH_Booking_Service $booking_service) {
        $this->calendar_service = $calendar_service;
        $this->booking_service = $booking_service;
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

        $events = $this->calendar_service->get_events(
            $_GET['start'] ?? null,
            $_GET['end'] ?? null,
            ['user'  => get_current_user_id(),
            ]);

        wp_send_json($events);
    }

    public function create_event(): void {

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

    private function authorize_admin(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }
    }

    private function authorize_user(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error('Login required', 401);
        }
    }


}
