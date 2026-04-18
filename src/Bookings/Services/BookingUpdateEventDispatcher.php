<?php

namespace MYVH\Bookings\Services;

use MYVH\Bookings\BookingStatus;
use MYVH\Events\BookingEvents;
use MYVH\Events\EventDispatcher;

if (!defined('ABSPATH')) exit;

class BookingUpdateEventDispatcher
{
    public function dispatch(array $data, ?string $old_status = null): void
    {
        $new_status = $data['status'] ?? '';
        $current_status = $old_status;

        if ($new_status == BookingStatus::CONFIRMED->value && $current_status != BookingStatus::CONFIRMED->value) {
            EventDispatcher::dispatch(
                BookingEvents::CONFIRMED,
                [
                    'booking_id' => $data['booking_id'],
                    'room_id' => $data['room_id'],
                    'start' => $data['start_time'],
                    'end' => $data['end_time'],
                ]
            );
        }

        if ($new_status == BookingStatus::CANCELLED->value && $current_status != BookingStatus::CANCELLED->value) {
            EventDispatcher::dispatch(
                BookingEvents::CANCELLED,
                [
                    'booking_id' => $data['booking_id'],
                    'room_id' => $data['room_id'],
                    'start' => $data['start_time'],
                    'end' => $data['end_time'],
                ]
            );
        }

        EventDispatcher::dispatch(
            BookingEvents::UPDATED,
            [
                'booking_id' => $data['booking_id'],
                'room_id' => $data['room_id'],
                'start' => $data['start_time'],
                'end' => $data['end_time'],
            ]
        );
    }
}
