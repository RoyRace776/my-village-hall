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

    public function next_available_slot(int $room_id, ?string $date = null, int $length_minutes = 60, ?string $requested_start_time = null) {
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
        $requested_start_seconds = $this->resolve_requested_start_seconds($requested_start_time, $step);

        if (is_wp_error($requested_start_seconds)) {
            return $requested_start_seconds;
        }

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

            $day_start_seconds = $open_seconds;
            if ($day_offset === 0 && $requested_start_seconds !== null) {
                if ($requested_start_seconds >= $close_seconds) {
                    continue;
                }

                $day_start_seconds = max($open_seconds, (int) $requested_start_seconds);
            }

            if ($day_start_seconds > $last_start_seconds) {
                continue;
            }

            for ($start_seconds = $day_start_seconds; $start_seconds <= $last_start_seconds; $start_seconds += ($step * 60)) {
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

    public function find_next_booking_slot(?int $room_id = null, ?string $date = null, int $length_minutes = 60, array $options = []) {
        $resolved_room_id = (int) ($room_id ?? 0);
        $is_recurring = !empty($options['is_recurring']);
        $requested_start_time = !empty($options['start_time']) ? sanitize_text_field((string) $options['start_time']) : null;

        if ($is_recurring) {
            $recurrence = [
                'type' => sanitize_text_field((string) ($options['recurrence_type'] ?? 'weekly')),
                'interval' => max(1, (int) ($options['recurrence_interval'] ?? 1)),
                'max_occurrences' => max(1, min(365, (int) ($options['max_occurrences'] ?? 10))),
            ];

            if (!empty($options['recurrence_end_date'])) {
                $recurrence['end_date'] = sanitize_text_field((string) $options['recurrence_end_date']);
            }

            if ($resolved_room_id > 0) {
                return $this->find_next_recurring_slot_for_room($resolved_room_id, $date, $length_minutes, $recurrence, $requested_start_time);
            }

            return $this->find_next_slot_across_rooms($date, $length_minutes, true, $recurrence, $requested_start_time);
        }

        if ($resolved_room_id > 0) {
            return $this->next_available_slot($resolved_room_id, $date, $length_minutes, $requested_start_time);
        }

        return $this->find_next_slot_across_rooms($date, $length_minutes, false, [], $requested_start_time);
    }

    private function find_next_slot_across_rooms(?string $date, int $length_minutes, bool $is_recurring, array $recurrence, ?string $requested_start_time = null) {
        $rooms = $this->get_searchable_rooms();

        if (empty($rooms)) {
            return new \WP_Error('validation', __('No rooms available for booking slot search', 'my-village-hall'));
        }

        $best_slot = null;
        $best_ts = null;

        foreach ($rooms as $room) {
            $candidate_room_id = (int) ($room['Id'] ?? 0);
            if ($candidate_room_id <= 0) {
                continue;
            }

            $candidate = $is_recurring
                ? $this->find_next_recurring_slot_for_room($candidate_room_id, $date, $length_minutes, $recurrence, $requested_start_time)
                : $this->next_available_slot($candidate_room_id, $date, $length_minutes, $requested_start_time);

            if (is_wp_error($candidate) || !is_array($candidate)) {
                continue;
            }

            $candidate_start = trim((string) ($candidate['start'] ?? ''));
            $candidate_ts = strtotime($candidate_start);
            if ($candidate_ts === false) {
                continue;
            }

            if ($best_ts === null || $candidate_ts < $best_ts) {
                $best_ts = $candidate_ts;
                $best_slot = $candidate;
            }
        }

        if (is_array($best_slot)) {
            return $best_slot;
        }

        return new \WP_Error(
            'validation',
            __('No available slot found in the next 7 days for the requested duration', 'my-village-hall')
        );
    }

    private function find_next_recurring_slot_for_room(int $room_id, ?string $date, int $length_minutes, array $recurrence, ?string $requested_start_time = null) {
        $single_slot = $this->next_available_slot($room_id, $date, $length_minutes, $requested_start_time);
        if (is_wp_error($single_slot)) {
            return $single_slot;
        }

        $base_date = trim((string) ($date ?: wp_date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $base_date)) {
            return new \WP_Error('validation', __('Date must be in YYYY-MM-DD format', 'my-village-hall'));
        }

        $room = $this->room_repo->get_by_id($room_id);
        if (!$room) {
            return new \WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        $type = (string) ($recurrence['type'] ?? 'weekly');
        $interval = max(1, (int) ($recurrence['interval'] ?? 1));
        $max_occurrences = max(1, min(365, (int) ($recurrence['max_occurrences'] ?? 10)));
        $end_date = !empty($recurrence['end_date']) ? (string) $recurrence['end_date'] : '';

        $valid_types = ['daily', 'weekly', 'monthly', 'yearly'];
        if (!in_array($type, $valid_types, true)) {
            $type = 'weekly';
        }

        $length = max(15, (int) $length_minutes);
        $step = 15;
        $requested_start_seconds = $this->resolve_requested_start_seconds($requested_start_time, $step);

        if (is_wp_error($requested_start_seconds)) {
            return $requested_start_seconds;
        }

        if (($length % $step) !== 0) {
            $length = (int) (ceil($length / $step) * $step);
        }

        $base_ts = strtotime($base_date . ' 00:00:00');
        if ($base_ts === false) {
            return new \WP_Error('validation', __('Date must be in YYYY-MM-DD format', 'my-village-hall'));
        }

        for ($day_offset = 0; $day_offset < 30; $day_offset++) {
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

            $day_start_seconds = $open_seconds;
            if ($day_offset === 0 && $requested_start_seconds !== null) {
                if ($requested_start_seconds >= $close_seconds) {
                    continue;
                }

                $day_start_seconds = max($open_seconds, (int) $requested_start_seconds);
            }

            if ($day_start_seconds > $last_start_seconds) {
                continue;
            }

            for ($start_seconds = $day_start_seconds; $start_seconds <= $last_start_seconds; $start_seconds += ($step * 60)) {
                $end_seconds = $start_seconds + ($length * 60);
                $start_time = gmdate('H:i:s', $start_seconds);
                $end_time = gmdate('H:i:s', $end_seconds);

                if (!$this->is_slot_valid_for_recurring_series($room_id, $target_date, $start_time, $end_time, $effective, $type, $interval, $max_occurrences, $end_date)) {
                    continue;
                }

                return [
                    'room_id' => $room_id,
                    'room_name' => (string) ($room['Name'] ?? ''),
                    'date' => $target_date,
                    'length_minutes' => $length,
                    'start_date' => $target_date,
                    'end_date' => $target_date,
                    'start_time' => substr($start_time, 0, 5),
                    'end_time' => substr($end_time, 0, 5),
                    'start' => $target_date . ' ' . substr($start_time, 0, 5),
                    'end' => $target_date . ' ' . substr($end_time, 0, 5),
                    'is_recurring' => 1,
                    'recurrence_type' => $type,
                    'recurrence_interval' => $interval,
                    'max_occurrences' => $max_occurrences,
                    'recurrence_end_date' => $end_date,
                ];
            }
        }

        return new \WP_Error(
            'validation',
            __('No available recurring slot found in the next 30 days for the requested duration', 'my-village-hall')
        );
    }

    private function is_slot_valid_for_recurring_series(
        int $room_id,
        string $start_date,
        string $start_time,
        string $end_time,
        array $start_day_hours,
        string $recurrence_type,
        int $interval,
        int $max_occurrences,
        string $end_date
    ): bool {
        $dates = $this->generate_recurring_dates($start_date, $recurrence_type, $interval, $max_occurrences, $end_date);
        if (empty($dates)) {
            return false;
        }

        foreach ($dates as $index => $occurrence_date) {
            $hours = $index === 0 ? $start_day_hours : $this->get_effective_room_hours_for_date($room_id, $occurrence_date);
            if (is_wp_error($hours) || !is_array($hours) || !empty($hours['is_closed'])) {
                return false;
            }

            $open_seconds = $this->time_to_seconds($hours['opening_time'] ?? '');
            $close_seconds = $this->time_to_seconds($hours['closing_time'] ?? '');
            $start_seconds = $this->time_to_seconds($start_time);
            $end_seconds = $this->time_to_seconds($end_time);

            if (
                $open_seconds === null ||
                $close_seconds === null ||
                $start_seconds === null ||
                $end_seconds === null ||
                $end_seconds <= $start_seconds ||
                $start_seconds < $open_seconds ||
                $end_seconds > $close_seconds
            ) {
                return false;
            }

            if (!$this->room_is_available($room_id, $occurrence_date, $start_time, $end_time, $occurrence_date, null)) {
                return false;
            }

            if (!$this->slot_has_buffer_space($room_id, $occurrence_date, $start_time, $end_time, $hours)) {
                return false;
            }
        }

        return true;
    }

    private function generate_recurring_dates(string $start_date, string $type, int $interval, int $max_occurrences, string $end_date = ''): array {
        $dates = [];
        $current = new \DateTimeImmutable($start_date);
        $end = null;

        if ($end_date !== '') {
            $end = \DateTimeImmutable::createFromFormat('Y-m-d', $end_date);
            if (!$end) {
                return [];
            }
        }

        for ($index = 0; $index < $max_occurrences; $index++) {
            if ($end !== null && $current > $end) {
                break;
            }

            $dates[] = $current->format('Y-m-d');

            switch ($type) {
                case 'daily':
                    $current = $current->modify('+' . $interval . ' day');
                    break;
                case 'monthly':
                    $current = $current->modify('+' . $interval . ' month');
                    break;
                case 'yearly':
                    $current = $current->modify('+' . $interval . ' year');
                    break;
                case 'weekly':
                default:
                    $current = $current->modify('+' . $interval . ' week');
                    break;
            }
        }

        return $dates;
    }

    private function get_searchable_rooms(): array {
        $rooms = $this->room_repo->get_all(['orderby' => 'Name', 'order' => 'ASC']);
        if (!is_array($rooms)) {
            return [];
        }

        return array_values(array_filter($rooms, static function ($room): bool {
            $room_id = (int) ($room['Id'] ?? 0);
            if ($room_id <= 0) {
                return false;
            }

            if (array_key_exists('IsActive', (array) $room)) {
                return (int) $room['IsActive'] === 1;
            }

            return true;
        }));
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

    private function resolve_requested_start_seconds(?string $requested_start_time, int $step_minutes): int|\WP_Error|null {
        $value = trim((string) ($requested_start_time ?? ''));
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
            return new \WP_Error('validation', __('Time must be in HH:MM format', 'my-village-hall'));
        }

        if (strlen($value) === 5) {
            $value .= ':00';
        }

        $seconds = $this->time_to_seconds($value);
        if ($seconds === null) {
            return new \WP_Error('validation', __('Time must be in HH:MM format', 'my-village-hall'));
        }

        $step_seconds = max(1, $step_minutes) * 60;
        $rounded = (int) (ceil($seconds / $step_seconds) * $step_seconds);

        return min($rounded, 86400);
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
