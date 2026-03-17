<?php

class MYVH_Portal_Calendar_Controller {

    private $calendar_service;

    public function __construct(MYVH_Calendar_Service $calendar_service) {
        $this->calendar_service = $calendar_service;
    }

    public function get_events() {

        if (!is_user_logged_in()) {
            wp_die('Unauthorized');
        }

        $start = $_GET['start'];
        $end   = $_GET['end'];

        $bookings = $this->calendar_service
            ->get_events($start, $end);

        $events = MYVH_Calendar_Event_Transformer::for_portal($bookings);

        wp_send_json($events);
    }
}
