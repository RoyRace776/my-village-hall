<?php
class MYVH_Room_Rules_Service {

    /**
     * Check if booking is within room opening hours.
     */
    public function within_opening_hours($room, $start, $end): bool {
        $room_open  = strtotime($room['OpeningTime']);
        $room_close = strtotime($room['ClosingTime']);
        $start_time = strtotime(date('H:i', strtotime($start)));
        $end_time   = strtotime(date('H:i', strtotime($end)));
        return ($start_time >= $room_open && $end_time <= $room_close);
    }

    /**
     * Check if the room allows multi-day bookings.
     */
    public function allows_multi_day($room): bool {
        return !empty($room['AllowMultiDayBookings']);
    }

    /**
     * Check if the booking duration is allowed (min/max duration).
     * Assumes $room['MinBookingMinutes'] and $room['MaxBookingMinutes'] (optional).
     */
    public function is_duration_allowed($room, $start, $end): bool {
        $start_ts = strtotime($start);
        $end_ts = strtotime($end);
        $duration = ($end_ts - $start_ts) / 60; // minutes
        if (!empty($room['MinBookingMinutes']) && $duration < $room['MinBookingMinutes']) {
            return false;
        }
        if (!empty($room['MaxBookingMinutes']) && $duration > $room['MaxBookingMinutes']) {
            return false;
        }
        return true;
    }

    /**
     * Check if the booking is on an allowed day (e.g., not a restricted weekday).
     * Assumes $room['AllowedDays'] is an array of allowed weekday numbers (0=Sunday).
     */
    public function is_day_allowed($room, $date): bool {
        if (empty($room['AllowedDays']) || !is_array($room['AllowedDays'])) {
            return true; // No restriction
        }
        $weekday = date('w', strtotime($date));
        return in_array($weekday, $room['AllowedDays'], true);
    }

    /**
     * Check if there is enough buffer time before/after the booking.
     * Assumes $room['BufferMinutes'] (optional).
     * This is a stub; actual implementation may require booking context.
     */
    public function has_buffer_time($room, $start, $end): bool {
        // Placeholder: always returns true. Implement conflict/buffer logic as needed.
        return true;
    }
}