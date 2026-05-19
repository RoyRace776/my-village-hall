<?php
namespace MYVH\Availability;
use MYVH\Bookings\BookingRepository;
use MYVH\Rooms\RoomHoursRepository;
use MYVH\Rooms\RoomRepository;
use MYVH\Venues\VenueHoursRepository;
use MYVH\Venues\VenueRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AvailabilityService {

    private $booking_repo;
    private $room_repo;
    private $room_hours_repo;
    private $venue_repo;
    private $venue_hours_repo;
    private LoggerInterface $logger;

    public function __construct(BookingRepository $booking_repo,
                                RoomRepository $room_repo,
                                RoomHoursRepository $room_hours_repo,
                                VenueRepository $venue_repo,
                                VenueHoursRepository $venue_hours_repo,
                                ?LoggerInterface $logger = null) {
        $this->booking_repo = $booking_repo;
        $this->room_repo = $room_repo;
        $this->room_hours_repo = $room_hours_repo;
        $this->venue_repo = $venue_repo;
        $this->venue_hours_repo = $venue_hours_repo;
        $this->logger = $logger ?? new NullLogger();
    }

    public function room_is_available( mixed $room_id, mixed $date, mixed $start, mixed $end, mixed $end_date = null, mixed $exclude_booking_id = null) {
        return !$this->booking_repo->has_conflict($room_id, $date, $start, $end, $exclude_booking_id, $end_date);
    }

    public function is_room_open( mixed $room_id, mixed $when) {
        $room = $this->room_repo->get_by_id($room_id);
        if (!$room) {
            return new \WP_Error('Availability', __('Unknown room passed to availability service', 'my-village-hall'));;
        }

        $when_ts = $this->time_to_seconds($when);
        $open_ts = $this->time_to_seconds($room['OpeningTime']);
        $close_ts = $this->time_to_seconds($room['ClosingTime']);

        if ($when_ts === null || $open_ts === null || $close_ts === null) {
            return new \WP_Error('Availability', __('Invalid time value passed to availability service', 'my-village-hall'));
        }

        if ($when_ts < $open_ts || $when_ts > $close_ts) {
            return false;
        }

        return true;
    }

    public function booking_within_opening_hours( mixed $room_id, mixed $start_time, mixed $end_time, mixed $start_date = null, mixed $end_date = null) {
        $room = $this->room_repo->get_by_id($room_id);
        if (!$room) {
            return new \WP_Error('Availability', __('Unknown room passed to availability service', 'my-village-hall'));
        }

        if (empty($start_date)) {
            $start_ts = $this->time_to_seconds($start_time);
            $end_ts = $this->time_to_seconds($end_time);
            $open_ts = $this->time_to_seconds($room['OpeningTime']);
            $close_ts = $this->time_to_seconds($room['ClosingTime']);

            if ($start_ts === null || $end_ts === null || $open_ts === null || $close_ts === null) {
                return new \WP_Error('Availability', __('Invalid booking or room opening time supplied', 'my-village-hall'));
            }

            return $start_ts >= $open_ts && $end_ts <= $close_ts;
        }

        $resolved_end_date = !empty($end_date) ? (string) $end_date : (string) $start_date;
        $start_dt = strtotime($start_date . ' ' . $start_time);
        $end_dt = strtotime($resolved_end_date . ' ' . $end_time);

        if ($start_dt === false || $end_dt === false) {
            return new \WP_Error('Availability', __('Invalid booking date/time supplied', 'my-village-hall'));
        }

        if ($end_dt <= $start_dt) {
            return false;
        }

        $cursor = new \DateTimeImmutable(date('Y-m-d', $start_dt));
        $last_day = new \DateTimeImmutable(date('Y-m-d', $end_dt));

        $day_seconds = defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400;

        while ($cursor <= $last_day) {
            $day_date = $cursor->format('Y-m-d');
            $day_start = strtotime($day_date . ' 00:00:00');
            $day_end = $day_start + $day_seconds;

            $segment_start = max($start_dt, $day_start);
            $segment_end = min($end_dt, $day_end);

            if ($segment_start < $segment_end) {
                $effective = $this->get_effective_room_hours_for_date($room_id, $day_date);
                if (is_wp_error($effective)) {
                    return $effective;
                }

                if (!empty($effective['is_closed'])) {
                    return false;
                }

                $open_ts = $this->time_to_seconds($effective['opening_time'] ?? '');
                $close_ts = $this->time_to_seconds($effective['closing_time'] ?? '');

                if ($open_ts === null || $close_ts === null) {
                    return new \WP_Error('Availability', __('Invalid booking or room opening time supplied', 'my-village-hall'));
                }

                $segment_start_seconds = $segment_start - $day_start;
                $segment_end_seconds = $segment_end - $day_start;

                if ($segment_start_seconds < $open_ts || $segment_end_seconds > $close_ts) {
                    return false;
                }
            }

            $cursor = $cursor->modify('+1 day');
        }

        return true;
    }

    public function room_opening_hours_by_day_allowed(array $room_hours_by_day, int $venue_id) {
        $venue = $this->venue_repo->get_by_id($venue_id);
        if (!$venue) {
            return new \WP_Error('Availability', __('Unknown venue passed to availability service', 'my-village-hall'));
        }

        $venue_hours = $this->index_hours_by_day($this->venue_hours_repo->get_by_venue($venue_id));

        foreach ($room_hours_by_day as $row) {
            $day_of_week = \intval($row['day_of_week'] ?? -1);
            if ($day_of_week < 0 || $day_of_week > 6) {
                continue;
            }

            if (!empty($row['use_venue_hours']) || !empty($row['is_closed'])) {
                continue;
            }

            $room_open_ts = $this->time_to_seconds($row['opening_time'] ?? '');
            $room_close_ts = $this->time_to_seconds($row['closing_time'] ?? '');
            if ($room_open_ts === null || $room_close_ts === null) {
                return new \WP_Error('Availability', __('Invalid opening or closing time supplied', 'my-village-hall'));
            }

            $venue_day = $venue_hours[$day_of_week] ?? [
                'IsClosed' => 0,
                'OpeningTime' => $venue['OpeningTime'] ?? '',
                'ClosingTime' => $venue['ClosingTime'] ?? '',
            ];

            if (!empty($venue_day['IsClosed'])) {
                return false;
            }

            $venue_open_ts = $this->time_to_seconds($venue_day['OpeningTime'] ?? '');
            $venue_close_ts = $this->time_to_seconds($venue_day['ClosingTime'] ?? '');
            if ($venue_open_ts === null || $venue_close_ts === null) {
                return new \WP_Error('Availability', __('Invalid opening or closing time supplied', 'my-village-hall'));
            }

            if ($room_open_ts < $venue_open_ts || $room_close_ts > $venue_close_ts) {
                return false;
            }
        }

        return true;
    }

    public function get_effective_room_hours_for_date(int $room_id, string $date) {
        $room = $this->room_repo->get_by_id($room_id);
        if (!$room) {
            return new \WP_Error('Availability', __('Unknown room passed to availability service', 'my-village-hall'));
        }

        $day_of_week = \intval(date('w', strtotime($date)));
        $room_hours = $this->index_hours_by_day($this->room_hours_repo->get_by_room($room_id));

        if (isset($room_hours[$day_of_week])) {
            $room_day = $room_hours[$day_of_week];
            if (!empty($room_day['UseVenueHours'])) {
                return $this->get_effective_venue_hours_for_date(intval($room['VenueId']), $date);
            }

            if (!empty($room_day['IsClosed'])) {
                return [
                    'is_closed' => 1,
                    'opening_time' => null,
                    'closing_time' => null,
                ];
            }

            return [
                'is_closed' => 0,
                'opening_time' => $room_day['OpeningTime'] ?? null,
                'closing_time' => $room_day['ClosingTime'] ?? null,
            ];
        }

        return [
            'is_closed' => 0,
            'opening_time' => $room['OpeningTime'] ?? null,
            'closing_time' => $room['ClosingTime'] ?? null,
        ];
    }

    public function get_effective_venue_hours_for_date(int $venue_id, string $date) {
        $venue = $this->venue_repo->get_by_id($venue_id);
        if (!$venue) {
            return new \WP_Error('Availability', __('Unknown venue passed to availability service', 'my-village-hall'));
        }

        $day_of_week = \intval(date('w', strtotime($date)));
        $venue_hours = $this->index_hours_by_day($this->venue_hours_repo->get_by_venue($venue_id));

        if (isset($venue_hours[$day_of_week])) {
            $venue_day = $venue_hours[$day_of_week];
            if (!empty($venue_day['IsClosed'])) {
                return [
                    'is_closed' => 1,
                    'opening_time' => null,
                    'closing_time' => null,
                ];
            }

            return [
                'is_closed' => 0,
                'opening_time' => $venue_day['OpeningTime'] ?? null,
                'closing_time' => $venue_day['ClosingTime'] ?? null,
            ];
        }

        return [
            'is_closed' => 0,
            'opening_time' => $venue['OpeningTime'] ?? null,
            'closing_time' => $venue['ClosingTime'] ?? null,
        ];
    }

    public function room_opening_hours_allowed( mixed $room_open, mixed $room_close, mixed $venue_id) {
        $venue = $this->venue_repo->get_by_id($venue_id);
        if (!$venue) {
            return new \WP_Error('Availability', __('Unknown venue passed to availability service', 'my-village-hall'));;
        }

        $room_open_ts = $this->time_to_seconds($room_open);
        $room_close_ts = $this->time_to_seconds($room_close);
        $venue_open_ts = $this->time_to_seconds($venue['OpeningTime']);
        $venue_close_ts = $this->time_to_seconds($venue['ClosingTime']);

        if ($room_open_ts === null || $room_close_ts === null || $venue_open_ts === null || $venue_close_ts === null) {
            return new \WP_Error('Availability', __('Invalid opening or closing time supplied', 'my-village-hall'));
        }

        if ($room_open_ts < $venue_open_ts || $room_close_ts > $venue_close_ts) {
            return false;
        }

        return true;
    }

    public function next_available_slot(int $room_id, ?string $date = null, int $length_minutes = 60) {
        if ($room_id <= 0) {
            return new \WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        $room = $this->room_repo->get_by_id($room_id);
        if (!$room) {
            return new \WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        $base_date = trim((string) ($date ?: wp_date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $base_date)) {
            return new \WP_Error('validation', __('Date must be in YYYY-MM-DD format', 'my-village-hall'));
        }

        $length = max(15, (int) $length_minutes);
        $step = 15;

        if (($length % $step) !== 0) {
            $length = (int) (ceil($length / $step) * $step);
        }

        $base_ts = strtotime($base_date . ' 00:00:00');
        if ($base_ts === false) {
            return new \WP_Error('validation', __('Date must be in YYYY-MM-DD format', 'my-village-hall'));
        }

        for ($day_offset = 0; $day_offset < 7; $day_offset++) {
            $target_date = wp_date('Y-m-d', strtotime('+' . $day_offset . ' day', $base_ts));

            $effective = $this->get_effective_room_hours_for_date($room_id, $target_date);
            if (is_wp_error($effective)) {
                return $effective;
            }

            if (!empty($effective['is_closed'])) {
                continue;
            }

            $open_seconds = $this->time_to_seconds($effective['opening_time'] ?? '');
            $close_seconds = $this->time_to_seconds($effective['closing_time'] ?? '');

            if ($open_seconds === null || $close_seconds === null || $close_seconds <= $open_seconds) {
                continue;
            }

            $last_start_seconds = $close_seconds - ($length * 60);
            if ($last_start_seconds < $open_seconds) {
                continue;
            }

            for ($start_seconds = $open_seconds; $start_seconds <= $last_start_seconds; $start_seconds += ($step * 60)) {
                $end_seconds = $start_seconds + ($length * 60);

                $start_time = gmdate('H:i:s', $start_seconds);
                $end_time = gmdate('H:i:s', $end_seconds);

                $is_available = $this->room_is_available(
                    $room_id,
                    $target_date,
                    $start_time,
                    $end_time,
                    $target_date,
                    null
                );

                if (!$is_available) {
                    continue;
                }

                $has_buffer_space = $this->slot_has_buffer_space(
                    $room_id,
                    $target_date,
                    $start_time,
                    $end_time,
                    $effective
                );

                if (!$has_buffer_space) {
                    continue;
                }

                return [
                    'room_id' => $room_id,
                    'date' => $target_date,
                    'length_minutes' => $length,
                    'start_date' => $target_date,
                    'end_date' => $target_date,
                    'start_time' => substr($start_time, 0, 5),
                    'end_time' => substr($end_time, 0, 5),
                    'start' => $target_date . ' ' . substr($start_time, 0, 5),
                    'end' => $target_date . ' ' . substr($end_time, 0, 5),
                ];
            }
        }

        return new \WP_Error(
            'validation',
            __('No available slot found in the next 7 days for the requested duration', 'my-village-hall')
        );
    }

    private function slot_has_buffer_space(
        int $room_id,
        string $date,
        string $start_time,
        string $end_time,
        array $effective_hours
    ): bool {
        $setup_minutes = (int) myvh_setting('booking.set_up_minutes', 0);
        $tidy_minutes = (int) myvh_setting('booking.tidy_up_minutes', 0);

        if ($setup_minutes <= 0 && $tidy_minutes <= 0) {
            return true;
        }

        $opening = date('H:i:s', strtotime('1970-01-01 ' . (string) ($effective_hours['opening_time'] ?? '00:00:00')));
        $closing = date('H:i:s', strtotime('1970-01-01 ' . (string) ($effective_hours['closing_time'] ?? '23:59:59')));

        if ($start_time === $opening) {
            $setup_minutes = 0;
        }

        if ($end_time === $closing) {
            $tidy_minutes = 0;
        }

        if ($setup_minutes <= 0 && $tidy_minutes <= 0) {
            return true;
        }

        $start_ts = strtotime($date . ' ' . $start_time);
        $end_ts = strtotime($date . ' ' . $end_time);

        if ($start_ts === false || $end_ts === false || $end_ts <= $start_ts) {
            return false;
        }

        $window_start = date('Y-m-d H:i:s', $start_ts - ($setup_minutes * 60));
        $window_end = date('Y-m-d H:i:s', $end_ts + ($tidy_minutes * 60));

        return !$this->booking_repo->has_conflict_in_buffer_window(
            $room_id,
            $window_start,
            $window_end,
            null
        );
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
        if (!preg_match('/^(\d{1,2})(?::?(\d{2}))(?::(\d{2}))?$/', (string) $value, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    private function time_to_hour_ceil($value): ?int {
        if (!preg_match('/^(\d{1,2})(?::?(\d{2}))(?::(\d{2}))?$/', (string) $value, $m)) {
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

    private function index_hours_by_day(array $rows): array {
        $indexed = [];

        foreach ($rows as $row) {
            $day_of_week = \intval($row['DayOfWeek'] ?? -1);
            if ($day_of_week < 0 || $day_of_week > 6) {
                continue;
            }

            $indexed[$day_of_week] = $row;
        }

        return $indexed;
    }

    public function get_time_options( mixed $selected = '', mixed $start_hour = 0, mixed $end_hour = 23, mixed $on_hour_only = false): string {
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
