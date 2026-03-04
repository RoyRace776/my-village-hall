<?php

class MYVH_Availability_Service {

    private $booking_repo;

    public function __construct($booking_repo) {
        $this->booking_repo = $booking_repo;
    }

    public function room_is_available($room_id, $start, $end) {
        return !$this->booking_repo->has_conflict($room_id, $start, $end);
    }

}
