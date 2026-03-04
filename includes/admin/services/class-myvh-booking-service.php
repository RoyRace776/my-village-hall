<?php
if (!defined('ABSPATH')) exit;

class MYVH_Booking_Service {

    private $room_service;
    private $rate_service;
    private $booking_repo;

    public function __construct(
        $room_service,
        $rate_service,
        $booking_repo
    ) {
        $this->room_service = $room_service;
        $this->rate_service = $rate_service;
        $this->booking_repo = $booking_repo;
    }

    public function create_booking($data) {

        $room_id   = intval($data['room_id']);
        $group_id  = intval($data['customer_group_id']);
        $start     = $data['start_time'];
        $end       = $data['end_time'];

        // 1️⃣ Validate room exists
        $room = $this->room_service->get($room_id);
        if (!$room) {
            return new WP_Error('invalid_room', 'Room not found');
        }

        // 2️⃣ Validate within opening hours
        if (!$this->within_opening_hours($room, $start, $end)) {
            return new WP_Error('invalid_time', 'Outside room opening hours');
        }

        // 3️⃣ Validate availability
        if (!$this->is_available($room_id, $start, $end)) {
            return new WP_Error('not_available', 'Room already booked');
        }

        // 4️⃣ Calculate price
        $price = $this->rate_service->calculate_price(
            $room_id,
            $group_id,
            $start,
            $end
        );

        if (is_wp_error($price)) {
            return $price;
        }

        // 5️⃣ Save booking
        $booking = [
            'RoomId'          => $room_id,
            'CustomerGroupId' => $group_id,
            'StartTime'       => $start,
            'EndTime'         => $end,
            'TotalAmount'     => $price,
            'Status'          => 'confirmed',
            'CreatedAt'       => current_time('mysql')
        ];

        return $this->booking_repo->create($booking);
    }

    private function within_opening_hours($room, $start, $end) {

        $room_open  = strtotime($room['OpeningTime']);
        $room_close = strtotime($room['ClosingTime']);

        $start_time = strtotime(date('H:i', strtotime($start)));
        $end_time   = strtotime(date('H:i', strtotime($end)));

        return ($start_time >= $room_open && $end_time <= $room_close);
    }

    private function is_available($room_id, $start, $end) {

        $conflicts = $this->booking_repo->get_conflicts(
            $room_id,
            $start,
            $end
        );

        return empty($conflicts);
    }
}