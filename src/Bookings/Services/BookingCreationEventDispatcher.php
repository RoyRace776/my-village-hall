<?php

namespace MYVH\Bookings\Services;

use MYVH\Bookings\BookingStatus;
use MYVH\Events\BookingEvents;
use MYVH\Events\EventDispatcher;

if (!defined('ABSPATH')) exit;

class BookingCreationEventDispatcher
{
    public function dispatch_created(int $booking_id, array $data): void
    {
        EventDispatcher::dispatch(
            BookingEvents::CREATED,
            [
                'booking_id' => $booking_id,
                'room_id' => $data['room_id'],
                'start' => $data['start_time'],
                'end' => $data['end_time'],
            ]
        );

        if (($data['status'] ?? '') == BookingStatus::CONFIRMED->value) {
            EventDispatcher::dispatch(
                BookingEvents::CONFIRMED,
                [
                    'booking_id' => $booking_id,
                    'room_id' => $data['room_id'],
                    'start' => $data['start_time'],
                    'end' => $data['end_time'],
                ]
            );
        }
    }

    public function dispatch_after_create(int $booking_id, array $data): void
    {
        EventDispatcher::dispatch(
            BookingEvents::AFTER_CREATE,
            $data + ['booking_id' => $booking_id]
        );
    }
}
