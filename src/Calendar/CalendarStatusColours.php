<?php
namespace MYVH\Calendar;

use MYVH\Bookings\BookingStatus;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CalendarStatusColours {

    /**
     * Canonical status-to-colour mapping for calendar event rendering.
     *
     * @return array<string, string>
     */
    public static function map(): array {
        return [
            BookingStatus::CONFIRMED->value => '#2271b1',
            BookingStatus::PENDING->value   => '#f0a500',
            BookingStatus::CANCELLED->value => '#9aa0a6',
            BookingStatus::COMPLETED->value => '#2d8f45',
        ];
    }
}
