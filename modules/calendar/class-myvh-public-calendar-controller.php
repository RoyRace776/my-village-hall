<?php

class MYVH_Public_Calendar_Controller {

    private $calendar_service;

    public function __construct(MYVH_Calendar_Service  $calendar_service) {
        $this->calendar_service = $calendar_service;
    }

    public function get_events(): void {

        $start = $_GET['start'];
        $end   = $_GET['end'];

        $bookings = $this->calendar_service
            ->get_events($start, $end);

        $events = MYVH_Calendar_Event_Transformer::for_public($bookings);

        wp_send_json($events);
    }
}
