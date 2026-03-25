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
        $this->venue_repo = $venue_repo;
    }

    public function room_is_available($room_id, $date, $start, $end, $end_date = null, $exclude_booking_id = null) {
        return !$this->booking_repo->has_conflict($room_id, $date, $start, $end, $exclude_booking_id, $end_date);
    }

    public function is_room_open($room_id, $when) {
        $room = $this->room_repo->get_by_id($room_id);
        if (!$room) {
            return new WP_Error('Availability', __('Unknown room passed to availability service', 'my-village-hall'));;
        }

        $when_ts = $this->time_to_seconds($when);
        $open_ts = $this->time_to_seconds($room['OpeningTime']);
        $close_ts = $this->time_to_seconds($room['ClosingTime']);

        if ($when_ts === null || $open_ts === null || $close_ts === null) {
            return new WP_Error('Availability', __('Invalid time value passed to availability service', 'my-village-hall'));
        }

        if ($when_ts < $open_ts || $when_ts > $close_ts) {
            return false;
        }

        return true;
    }

    public function booking_within_opening_hours($room_id, $start_time, $end_time) {
        $room = $this->room_repo->get_by_id($room_id);
        if (!$room) {
            return new WP_Error('Availability', __('Unknown room passed to availability service', 'my-village-hall'));
        }

        $start_ts = $this->time_to_seconds($start_time);
        $end_ts = $this->time_to_seconds($end_time);
        $open_ts = $this->time_to_seconds($room['OpeningTime']);
        $close_ts = $this->time_to_seconds($room['ClosingTime']);

        if ($start_ts === null || $end_ts === null || $open_ts === null || $close_ts === null) {
            return new WP_Error('Availability', __('Invalid booking or room opening time supplied', 'my-village-hall'));
        }

        return $start_ts >= $open_ts && $end_ts <= $close_ts;
    }

    public function room_opening_hours_allowed($room_open, $room_close, $venue_id) {
        $venue = $this->venue_repo->get_by_id($venue_id);
        if (!$venue) {
            return new WP_Error('Availability', __('Unknown venue passed to availability service', 'my-village-hall'));;
        }

        $room_open_ts = $this->time_to_seconds($room_open);
        $room_close_ts = $this->time_to_seconds($room_close);
        $venue_open_ts = $this->time_to_seconds($venue['OpeningTime']);
        $venue_close_ts = $this->time_to_seconds($venue['ClosingTime']);

        if ($room_open_ts === null || $room_close_ts === null || $venue_open_ts === null || $venue_close_ts === null) {
            return new WP_Error('Availability', __('Invalid opening or closing time supplied', 'my-village-hall'));
        }

        if ($room_open_ts < $venue_open_ts || $room_close_ts > $venue_close_ts) {
            return false;
        }

        return true;
    }

    public function get_calendar_visible_hours(): array {
        $defaults = [
            'start' => 8,
            'end' => 22,
        ];

        $venues = $this->venue_repo->get_all();

        if (empty($venues) || !is_array($venues)) {
            return $defaults;
        }

        $start = 24;
        $end = 0;
        $found = false;

        foreach ($venues as $venue) {
            $open_hour = $this->time_to_hour_floor($venue['OpeningTime'] ?? '');
            $close_hour = $this->time_to_hour_ceil($venue['ClosingTime'] ?? '');

            if ($open_hour === null || $close_hour === null) {
                continue;
            }

            $found = true;
            $start = min($start, $open_hour);
            $end = max($end, $close_hour);
        }

        if (!$found) {
            return $defaults;
        }

        $start = max(0, min(23, (int) $start));
        $end = max(1, min(24, (int) $end));

        if ($end <= $start) {
            $end = min(24, $start + 1);
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function time_to_seconds($time): ?int {
        $value = trim((string) $time);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime('1970-01-01 ' . $value);

        if ($timestamp === false) {
            return null;
        }

        return (int) date('G', $timestamp) * 3600
            + (int) date('i', $timestamp) * 60
            + (int) date('s', $timestamp);
    }

    private function time_to_hour_floor($value): ?int {
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', (string) $value, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    private function time_to_hour_ceil($value): ?int {
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', (string) $value, $m)) {
            return null;
        }

        $hour = (int) $m[1];
        $minute = (int) $m[2];
        $second = isset($m[3]) ? (int) $m[3] : 0;

        if ($minute > 0 || $second > 0) {
            $hour++;
        }

        return $hour;
    }

    public function get_time_options($selected = '', $start_hour = 0, $end_hour = 23, $on_hour_only = false): string {
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
