<?php

namespace MYVH\Bookings\Services;

use MYVH\Events\BookingEvents;
use MYVH\Events\EventDispatcher;

if (!defined('ABSPATH')) exit;

class BookingStatusTransitionDispatcher
{
    public function dispatch_before(int $booking_id, ?string $old_status, string $new_status, array $data): void
    {
        if ($old_status === null || $old_status === $new_status) {
            return;
        }

        EventDispatcher::dispatch(
            BookingEvents::BEFORE_STATUS_CHANGE,
            [
                'booking_id' => $booking_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'data' => $data,
            ]
        );
    }

    public function dispatch_after(int $booking_id, ?string $old_status, string $new_status, array $data): void
    {
        if ($old_status === null || $old_status === $new_status) {
            return;
        }

        EventDispatcher::dispatch(
            BookingEvents::AFTER_STATUS_CHANGE,
            [
                'booking_id' => $booking_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'data' => $data,
            ]
        );
    }
}
