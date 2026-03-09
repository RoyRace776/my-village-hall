<?php
class MYVH_Booking_Validator {

    public function validate($data) {

        if (empty($data['room_id'])) {
            return new WP_Error('invalid_room');
        }

        if ($data['start_time'] >= $data['end_time']) {
            return new WP_Error('invalid_time_range');
        }

        return true;
    }

}