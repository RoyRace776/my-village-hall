<?php

class MYVH_Admin_Calendar_Controller {

    private $calendar_service;

    public function __construct(MYVH_Calendar_Service $calendar_service) {
        $this->calendar_service = $calendar_service;
    }

    public function get_events() {

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $start = $_GET['start'];
        $end   = $_GET['end'];

        $filters = [
            'include_cancelled' => true,
            'include_pending'   => true,
        ];

        $bookings = $this->calendar_service
            ->get_events($start, $end, $filters);

        $events = MYVH_Calendar_Event_Transformer::for_admin($bookings);

        wp_send_json($events);
    }
}
