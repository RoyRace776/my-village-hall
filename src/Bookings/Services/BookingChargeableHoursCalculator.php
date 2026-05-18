<?php

namespace MYVH\Bookings\Services;

use DateTime;

if (!defined('ABSPATH')) exit;

class BookingChargeableHoursCalculator
{
    public function calculate( mixed $startdate, mixed $start_time, mixed $enddate, mixed $end_time, mixed $room): float
    {
        $start = new DateTime($startdate . ' ' . $start_time);
        $end = new DateTime($enddate . ' ' . $end_time);
        $interval = $start->diff($end);

        $hours =
            ($interval->days * 24) +
            $interval->h +
            ($interval->i / 60);

        if (empty($room['CalcClosedHours'])) {
            $room_open = strtotime((string) ($room['OpeningTime'] ?? ''));
            $room_close = strtotime((string) ($room['ClosingTime'] ?? ''));

            if ($room_open !== false && $room_close !== false) {
                $open_hours = ($room_close - $room_open) / 3600;

                // Handle schedules that wrap past midnight (e.g. 20:00 to 02:00).
                if ($open_hours <= 0) {
                    $open_hours += 24;
                }

                $closed_hours_per_day = max(0, 24 - $open_hours);

                $start_day = (clone $start)->setTime(0, 0, 0);
                $end_day = (clone $end)->setTime(0, 0, 0);
                $days_crossed = $start_day->diff($end_day)->days;

                $hours -= ($closed_hours_per_day * $days_crossed);
            }
        }

        return max(0, $hours);
    }
}
