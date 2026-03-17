<?php

class MYVH_Calendar_Controller {

    private $calendar_service;

    public function __construct(MYVH_Calendar_Service $calendar_service) {
        $this->calendar_service = $calendar_service;
    }

    public function get_events() {

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
