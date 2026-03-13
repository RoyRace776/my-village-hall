<?php

class MYVH_Availability_Service {

    private $booking_repo;
    private $room_repo;
    private $venue_repo;

    public function __construct(MYVH_Booking_Repository $booking_repo,
                                MYVH_Room_Repository $room_repo,
                                MYVH_Venue_Repository $venue_repo) {
        $this->booking_repo = $booking_repo;
        $this->room_repo = $room_repo;
    }

    public function room_is_available($room_id, $date, $start, $end) {
        return !$this->booking_repo->has_conflict($room_id, $date, $start, $end);
    }

    public function is_room_open($room_id, $when) {
        $room = $this->room_repo->get_by_id($room_id);
        if (!$room) {
            return new WP_Error('Availability', __('Unknown room passed to availability service', 'my-village-hall'));;
        }

        if ( $when < $room['OpeningTime'] or $when > $room['ClosingTime']) {
            return false;
        }

        return true;
    }

    public function room_opening_hours_allowed($room_open, $room_close, $venue_id) {
        $venue = $this->venue_repo->get_by_id($venue_id);
        if (!$venue) {
            return new WP_Error('Availability', __('Unknown venue passed to availability service', 'my-village-hall'));;
        }

        if ($room_open < $venue['OpeningTime'] or $room_close > $venue['ClosingTime']) {
            return false;
        }

        return true;
    }

    public function get_time_options($selected = '', $start_hour = 0, $end_hour = 23, $on_hour_only = false) {
        $options = '';

        for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
            $hour_str = str_pad($hour, 2, '0', STR_PAD_LEFT);

            if ($on_hour_only) {
                // Only show times on the hour (for opening/closing times)
                $time = $hour_str . ':00';
                $selected_attr = ($time == substr($selected, 0, 5)) ? ' selected' : '';
                $options .= '<option value="' . $time . '"' . $selected_attr . '>' . $time . '</option>';
            } else {
                // Show all quarter-hour increments
                $minutes = array('00', '15', '30', '45');

                foreach ($minutes as $minute) {
                    $time = $hour_str . ':' . $minute;
                    $selected_attr = ($time == substr($selected, 0, 5)) ? ' selected' : '';
                    $options .= '<option value="' . $time . '"' . $selected_attr . '>' . $time . '</option>';
                }
            }
        }

        return $options;
    }

}
