<?php

class PortalCalendarController {

    private $calendar_service;

    public function __construct(CalendarService $calendar_service) {
        $this->calendar_service = $calendar_service;
    }

    public function get_events(): void {

        if (!is_user_logged_in()) {
            wp_die('Unauthorized');
        }

        $start = $_GET['start'];
        $end   = $_GET['end'];

        $bookings = $this->calendar_service
            ->get_events($start, $end);

        $events = CalendarEventTransformer::for_portal($bookings);

        wp_send_json($events);
    }
}
