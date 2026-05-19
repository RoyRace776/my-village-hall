<?php
namespace MYVH\Rooms;

use MYVH\Bookings\BookingRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RoomRulesService {

    private BookingRepository $booking_repo;
    private LoggerInterface $logger;

    public function __construct( BookingRepository $booking_repo, ?LoggerInterface $logger = null ) {
        $this->booking_repo = $booking_repo;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Check if booking is within room opening hours.
     */
    public function within_opening_hours( mixed $room, mixed $start, mixed $end): bool {
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
    public function is_duration_allowed( mixed $room, mixed $start, mixed $end): bool {
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
    public function is_day_allowed( mixed $room, mixed $date): bool {
        if (empty($room['AllowedDays']) || !is_array($room['AllowedDays'])) {
            return true; // No restriction
        }
        $weekday = date('w', strtotime($date));
        return in_array($weekday, $room['AllowedDays'], true);
    }

    /**
     * Check if there is enough buffer time before and after the booking.
     *
     * Uses global setup/tidy minute settings from Booking › Buffers, with exact
     * boundary exemptions: setup is waived when the booking starts precisely at
     * the room's opening time, and tidy is waived when it ends at closing time.
     *
     * Returns true if no conflicting booking occupies the required buffer window.
     *
     * @param array    $room              Room record (must include Id, OpeningTime, ClosingTime).
     * @param string   $start             Booking start as 'Y-m-d H:i' string.
     * @param string   $end               Booking end as 'Y-m-d H:i' string.
     * @param int|null $exclude_booking_id Booking to ignore (for updates).
     */
    public function has_buffer_time( mixed $room, mixed $start, mixed $end, ?int $exclude_booking_id = null ): bool {
        $setup_minutes = (int) myvh_setting( 'booking.set_up_minutes', 0 );
        $tidy_minutes  = (int) myvh_setting( 'booking.tidy_up_minutes', 0 );

        if ( $setup_minutes <= 0 && $tidy_minutes <= 0 ) {
            return true;
        }

        // Normalise times to H:i:s for exact boundary comparisons.
        $booking_start = date( 'H:i:s', strtotime( $start ) );
        $booking_end   = date( 'H:i:s', strtotime( $end ) );
        $room_open     = date( 'H:i:s', strtotime( '1970-01-01 ' . ( $room['OpeningTime'] ?? '00:00:00' ) ) );
        $room_close    = date( 'H:i:s', strtotime( '1970-01-01 ' . ( $room['ClosingTime'] ?? '23:59:59' ) ) );

        if ( $booking_start === $room_open ) {
            $setup_minutes = 0;
        }
        if ( $booking_end === $room_close ) {
            $tidy_minutes = 0;
        }

        if ( $setup_minutes <= 0 && $tidy_minutes <= 0 ) {
            return true;
        }

        $room_id = (int) ( $room['Id'] ?? 0 );
        if ( $room_id <= 0 ) {
            return true;
        }

        $start_ts = strtotime( $start );
        $end_ts   = strtotime( $end );

        $window_start = date( 'Y-m-d H:i:s', $start_ts - $setup_minutes * 60 );
        $window_end   = date( 'Y-m-d H:i:s', $end_ts   + $tidy_minutes  * 60 );

        return ! $this->booking_repo->has_conflict_in_buffer_window(
            $room_id,
            $window_start,
            $window_end,
            $exclude_booking_id
        );
    }
}
