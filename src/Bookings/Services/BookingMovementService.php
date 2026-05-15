<?php

namespace MYVH\Bookings\Services;

use MYVH\Availability\AvailabilityService;
use MYVH\Bookings\BookingRepository;
use MYVH\Rooms\RoomService;
use WP_Error;

if (!defined('ABSPATH')) exit;

class BookingMovementService
{
    private $room_service;
    private $availability;
    private $booking_repo;

    public function __construct(RoomService $room_service, AvailabilityService $availability, BookingRepository $booking_repo)
    {
        $this->room_service = $room_service;
        $this->availability = $availability;
        $this->booking_repo = $booking_repo;
    }

    public function move_booking($id, $start, $end, $room): int|WP_Error
    {
        $room_id = \intval($room);

        [$start_date, $start_time] = $this->split_datetime($start);
        [$end_date, $end_time] = $this->split_datetime($end, $start_date);

        if (!$room_id || !$start_date || !$start_time || !$end_date || !$end_time) {
            return new WP_Error('validation', __('Invalid booking movement data', 'my-village-hall'));
        }

        $room_record = $this->room_service->get($room_id);
        if (!$room_record) {
            return new WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        if ($start_date !== $end_date && empty($room_record['AllowMultiDayBookings'])) {
            return new WP_Error('validation', __('Multi day bookings are not allowed for this room', 'my-village-hall'));
        }

        $within_opening_hours = $this->availability->booking_within_opening_hours(
            $room_id,
            $start_time,
            $end_time,
            $start_date,
            $end_date
        );

        if (is_wp_error($within_opening_hours)) {
            return $within_opening_hours;
        }

        if (!$within_opening_hours) {
            return new WP_Error(
                'validation',
                sprintf(
                    __('Booking times must be within the room opening hours of %1$s to %2$s.', 'my-village-hall'),
                    substr((string) $room_record['OpeningTime'], 0, 5),
                    substr((string) $room_record['ClosingTime'], 0, 5)
                )
            );
        }

        $is_available = $this->availability->room_is_available(
            $room_id,
            $start_date,
            $start_time,
            $end_time,
            $end_date,
            \intval($id)
        );

        if (!$is_available) {
            return new WP_Error('validation', __('This room is not available for the selected time.', 'my-village-hall'));
        }

        $result = $this->booking_repo->move_booking($id, $start, $end, $room);

        if ($result != 1) {
            return new WP_Error('Move booking', __('Something went wrong', 'my-village-hall'));
        }

        return $result;
    }

    private function split_datetime($value, $default_date = ''): array
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return [sanitize_text_field($default_date), ''];
        }

        $timestamp = strtotime($raw);

        if ($timestamp !== false) {
            return [
                date('Y-m-d', $timestamp),
                date('H:i:s', $timestamp),
            ];
        }

        $parts = preg_split('/[T\s]/', $raw);
        $date = sanitize_text_field($parts[0] ?? $default_date);
        $time = sanitize_text_field($parts[1] ?? '');

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }

        return [$date, $time];
    }
}
