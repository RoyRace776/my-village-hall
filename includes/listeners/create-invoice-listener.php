<?php

class MYVH_Create_Invoice_Listener {

    public function register() {
        add_action(
            'myvh_event_booking.created',
            [$this, 'handle']
        );
    }

    public function handle($payload) {

        $booking_id = $payload['booking_id'];

        // generate invoice here
    }

}