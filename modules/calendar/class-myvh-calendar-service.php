<?php

class MYVH_Calendar_Service {

    private $booking_repository;

    public function __construct(MYVH_Booking_Repository $booking_repository) {
        $this->booking_repository = $booking_repository;
    }

    public function get_events($start, $end, $filters = []) {

        $bookings = $this->booking_repository
            ->get_between($start, $end, $filters);

        return $bookings;
    }
}
