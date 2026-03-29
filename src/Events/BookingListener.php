<?php
namespace MYVH\Events;

class BookingListener {

    public function register(): void {
        add_action(
            'myvh_event_booking.created',
            [$this, 'handle_booking_created']
        );

        add_action(
            'myvh_event_booking.confirmed',
            [$this, 'handle_booking_confirmed']
        );

        add_action(
            'myvh_event_booking.cancelled',
            [$this, 'handle_booking_cancelled']
        );

        add_action(
            'myvh_event_booking.updated',
            [$this, 'handle_booking_updated']
        );
    }

    public function handle_booking_created($payload): void {

        $booking_id = $payload['booking_id'];

        // Processing here
    }

    public function handle_booking_confirmed($payload): void {

        $booking_id = $payload['booking_id'];

        // Processing here
    }

    public function handle_booking_cancelled($payload): void {

        $booking_id = $payload['booking_id'];

        // Processing here
    }

    public function handle_booking_updated($payload): void {

        $booking_id = $payload['booking_id'];

        // Processing here
    }
}