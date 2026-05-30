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
        $has_send_confirmation_flag = array_key_exists('send_confirmation_email', $data);
        $send_confirmation_email = !isset($data['send_confirmation_email']) || \intval($data['send_confirmation_email']) === 1;
        $status_is_transition_to_confirmed = $current_status != BookingStatus::CONFIRMED->value;
        $status_is_still_confirmed = $current_status == BookingStatus::CONFIRMED->value;

        if (
            $new_status == BookingStatus::CONFIRMED->value
            && $send_confirmation_email
            && ($status_is_transition_to_confirmed || ($status_is_still_confirmed && $has_send_confirmation_flag))
        ) {
            EventDispatcher::dispatch(
                BookingEvents::CONFIRMED,
                [
                    'booking_id' => $data['booking_id'],
                    'room_id' => $data['room_id'],
                    'start' => $data['start_time'],
                    'end' => $data['end_time'],
                    'send_confirmation_email' => 1,
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
