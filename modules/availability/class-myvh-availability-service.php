<?php

class MYVH_Availability_Service {

    private $booking_repo;
    private $room_repo;

    public function __construct(MYVH_Booking_Repository $booking_repo,
                                MYVH_Room_Repository $room_repo) {
        $this->booking_repo = $booking_repo;
        $this->room_repo = $room_repo;
    }

    public function room_is_available($room_id, $date, $start, $end) {
        return !$this->booking_repo->has_conflict($room_id, $date, $start, $end);
    }

}
