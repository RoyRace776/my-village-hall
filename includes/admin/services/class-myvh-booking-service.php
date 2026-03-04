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

    public function save($data) {
        global $myvh_recurring_pattern_service;

        // Validation
        if (empty($data['customer_id'])) {
            return new WP_Error('validation', __('Customer is required', 'my-village-hall'));
        }

        if (empty($data['room_id'])) {
            return new WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        if (empty($data['start_date'])) {
            return new WP_Error('validation', __('Start date is required', 'my-village-hall'));
        }

        if (empty($data['start_time']) || empty($data['end_time'])) {
            return new WP_Error('validation', __('Start and end times are required', 'my-village-hall'));
        }

        // Validate time
        $start = strtotime($data['start_time']);
        $end = strtotime($data['end_time']);
        if ($end <= $start) {
            return new WP_Error('validation', __('End time must be after start time', 'my-village-hall'));
        }

        $record = [
            'CustomerId'  => intval($data['customer_id']),
            'RoomId'      => intval($data['room_id']),
            'Status'      => sanitize_text_field($data['status'] ?? 'confirmed'),
            'StartDate'   => sanitize_text_field($data['start_date']),
            'EndDate'     => !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : sanitize_text_field($data['start_date']),
            'StartTime'   => sanitize_text_field($data['start_time']),
            'EndTime'     => sanitize_text_field($data['end_time']),
            'Description' => sanitize_textarea_field($data['description'] ?? ''),
            'Public'      => isset($data['public']) ? 1 : 0,
        ];

        // Update existing booking
        if (!empty($data['booking_id'])) {
            $result = $this->booking_repo->update($record, ['Id' => intval($data['booking_id'])]);
            if ($result === false) {
                return new WP_Error('database', __('Failed to update booking', 'my-village-hall'));
            }
            return intval($data['booking_id']);
        }

        // Create new booking
        $booking_id = $this->booking_repo->create($record);
        if ($booking_id === false) {
            return new WP_Error('database', __('Failed to create booking', 'my-village-hall'));
        }

        // Handle recurring pattern if requested
        if (!empty($data['is_recurring']) && $myvh_recurring_pattern_service) {
            $pattern_data = [
                'parent_booking_id'   => $booking_id,
                'recurrence_type'     => sanitize_text_field($data['recurrence_type']),
                'recurrence_interval' => intval($data['recurrence_interval']),
                'start_date'          => sanitize_text_field($data['start_date']),
                'is_active'           => 1,
            ];

            // Set end condition
            if ($data['recurrence_end_type'] === 'date') {
                $pattern_data['end_date'] = sanitize_text_field($data['recurrence_end_date']);
            } else {
                $pattern_data['max_occurrences'] = intval($data['max_occurrences']);
            }

            $myvh_recurring_pattern_service->save($pattern_data);
        }

        return $booking_id;
    }

    public function cancel($id) {
        return $this->booking_repo->update(
            ['Status' => 'cancelled'],
            ['Id' => $id]
        );
    }

}