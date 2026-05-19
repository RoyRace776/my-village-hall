<?php
namespace MYVH\Pricing;

use MYVH\Customers\CustomerRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if (!\defined('ABSPATH')) exit;

class RoomRateService {

    private RoomRateRepository $repo;
    private LoggerInterface $logger;

    public function __construct(RoomRateRepository $repo, ?LoggerInterface $logger = null) {
        $this->repo = $repo;
        $this->logger = $logger ?? new NullLogger();
    }

    public function get_all(): array {
        return $this->repo->get_all([
            'orderby' => 'Priority',
            'order' => 'DESC',
        ]);
    }

    public function get_by_room(int $room_id): array {
        return $this->repo->get_by_room($room_id);
    }

    public function get_room_ids_with_active_rates(array $room_ids = []): array {
        return $this->repo->get_room_ids_with_active_rates($room_ids);
    }

    public function get(int $id): ?array {
        return $this->repo->get_by_id($id);
    }

    public function save(array $data): int|\WP_Error {

        if (empty($data['room_id'])) {
            return new \WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        if (empty($data['name'])) {
            return new \WP_Error('validation', __('Rate name is required', 'my-village-hall'));
        }

        $charge_type = sanitize_text_field($data['charge_type'] ?? '');
        if (!\in_array($charge_type, ['per_hour', 'per_day', 'fixed'])) {
            return new \WP_Error('validation', __('Invalid charge type', 'my-village-hall'));
        }

        $rate = \floatval($data['rate'] ?? 0);
        if ($rate < 0) {
            return new \WP_Error('validation', __('Rate must be zero or greater', 'my-village-hall'));
        }

        $minimum_hours = $data['minimum_hours'] ?? null;
        if (!isset($data['minimum_hours']) || \floatval($minimum_hours) <= 0) {
            return new \WP_Error('validation', __('Minimum hours must be greater than zero', 'my-village-hall'));
        }

        $days_of_week = $this->normalize_days_of_week($data);
        if ($days_of_week === null) {
            return new \WP_Error('validation', __('Days of week must be between 0 and 6', 'my-village-hall'));
        }

        $start_time_raw = trim((string) ($data['start_time'] ?? ''));
        $end_time_raw = trim((string) ($data['end_time'] ?? ''));

        $start_time = $this->normalize_time_value($start_time_raw);
        $end_time = $this->normalize_time_value($end_time_raw);

        if ($start_time_raw !== '' && $start_time === null) {
            return new \WP_Error('validation', __('Start time must use HH:MM or HH:MM:SS format', 'my-village-hall'));
        }

        if ($start_time !== null && !$this->is_quarter_hour_time($start_time)) {
            return new \WP_Error('validation', __('Start time must use 15 minute intervals', 'my-village-hall'));
        }

        if ($end_time_raw !== '' && $end_time === null) {
            return new \WP_Error('validation', __('End time must use HH:MM or HH:MM:SS format', 'my-village-hall'));
        }

        if ($end_time !== null && !$this->is_quarter_hour_time($end_time)) {
            return new \WP_Error('validation', __('End time must use 15 minute intervals', 'my-village-hall'));
        }

        $has_schedule_fields = !empty($days_of_week) || $start_time !== null || $end_time !== null;
        if ($has_schedule_fields && $charge_type === 'fixed') {
            return new \WP_Error('validation', __('Fixed rates cannot be limited to a day/time schedule', 'my-village-hall'));
        }

        if (($start_time === null) xor ($end_time === null)) {
            return new \WP_Error('validation', __('Start and end time must be provided together', 'my-village-hall'));
        }

        if ($start_time !== null && $end_time !== null && strcmp($end_time, $start_time) <= 0) {
            return new \WP_Error('validation', __('End time must be later than start time', 'my-village-hall'));
        }

        $legacy_day_of_week = count($days_of_week) === 1 ? $days_of_week[0] : null;

        $record = [
            'RoomId'             => \intval($data['room_id']),
            'OrganisationTypeId' => !empty($data['organisation_type_id']) ? \intval($data['organisation_type_id']) : null,
            'DayOfWeek'          => $legacy_day_of_week,
            'StartTime'          => $start_time,
            'EndTime'            => $end_time,
            'ChargeType'         => $charge_type,
            'Rate'               => $rate,
            'Name'               => sanitize_text_field($data['name']),
            'Description'        => sanitize_textarea_field($data['description'] ?? ''),
            'MinimumHours'       => \floatval($minimum_hours),
            'IsActive'           => isset($data['is_active']) ? 1 : 0,
            'ValidFrom'          => !empty($data['valid_from']) ? sanitize_text_field($data['valid_from']) : null,
            'ValidTo'            => !empty($data['valid_to'])   ? sanitize_text_field($data['valid_to'])   : null,
            'Priority'           => \intval($data['priority'] ?? 0),
        ];

        if (!empty($data['rate_id'])) {
            $rate_id = \intval($data['rate_id']);
            $result = $this->repo->update($record, ['Id' => \intval($data['rate_id'])]);
            if (is_wp_error($result)) {
                return $result;
            }

            if (!$result) {
                return new \WP_Error('database', __('Failed to save rate', 'my-village-hall'));
            }

            if (!$this->repo->replace_days_for_rate($rate_id, $days_of_week)) {
                return new \WP_Error('database', __('Failed to save rate days', 'my-village-hall'));
            }

            return $rate_id;
        }

        $id = $this->repo->create($record);
        if (!$id) {
            return new \WP_Error('database', __('Failed to save rate', 'my-village-hall'));
        }

        if (!$this->repo->replace_days_for_rate((int) $id, $days_of_week)) {
            return new \WP_Error('database', __('Failed to save rate days', 'my-village-hall'));
        }

        return $id;
    }

    public function get_booking_rate(
        int $room_id,
        array $customer,
        array $organisation,
        ?string $start_date = null,
        ?string $start_time = null,
        ?string $end_date = null,
        ?string $end_time = null,
        ?string $validity_reference_date = null
    ): ?array {

        $org_type_id = \intval($organisation['OrganisationTypeId'] ?? 0);

        if (
            !empty($start_date)
            && !empty($start_time)
            && !empty($end_date)
            && !empty($end_time)
        ) {
            $resolved = $this->resolve_rate_for_slot(
                $room_id,
                $org_type_id > 0 ? $org_type_id : null,
                $start_date,
                $start_time,
                $end_date,
                $end_time,
                $validity_reference_date ?: $start_date
            );

            if ($resolved) {
                return $resolved;
            }
        }

        // First try a rate specific to this room + organisation type
        $rate = $org_type_id > 0
            ? $this->repo->get_active_room_rate($room_id, $org_type_id)
            : null;

        // Fall back to a rate with no organisation type restriction
        if ( !$rate ) {
            $rate = $this->repo->get_active_room_rate( $room_id );
        }

        return $rate;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_active_rates_for_scope(int $room_id, ?int $org_type_id = null, ?string $validity_date = null): array {
        return $this->repo->get_active_rates_for_scope($room_id, $org_type_id, $validity_date);
    }

    /**
     * Resolve a single rate that matches the provided slot.
     */
    public function resolve_rate_for_slot(
        int $room_id,
        ?int $org_type_id,
        string $slot_start_date,
        string $slot_start_time,
        string $slot_end_date,
        string $slot_end_time,
        ?string $validity_reference_date = null
    ): ?array {
        $slot_start = $this->create_datetime($slot_start_date, $slot_start_time);
        $slot_end = $this->create_datetime($slot_end_date, $slot_end_time);
        if (!$slot_start || !$slot_end || $slot_end <= $slot_start) {
            return null;
        }

        $validity_date = $validity_reference_date ?: $slot_start->format('Y-m-d');
        $org_specific = $org_type_id ? $this->repo->get_active_rates_for_scope($room_id, $org_type_id, $validity_date) : [];
        $all_types = $this->repo->get_active_rates_for_scope($room_id, null, $validity_date);

        $matched = $this->match_rate_for_slot($org_specific, $slot_start, $slot_end);
        if ($matched) {
            return $matched;
        }

        return $this->match_rate_for_slot($all_types, $slot_start, $slot_end);
    }

    /**
     * @param array<int, array<string, mixed>> $rates
     */
    public function match_rate_for_slot(array $rates, \DateTimeImmutable $slot_start, \DateTimeImmutable $slot_end): ?array {
        foreach ($rates as $rate) {
            if ($this->rate_matches_slot($rate, $slot_start, $slot_end)) {
                return $rate;
            }
        }

        return null;
    }

    public function delete(int $id) {
        return $this->repo->delete($id);
    }

    /**
     * @param array<string, mixed> $rate
     */
    private function rate_matches_slot(array $rate, \DateTimeImmutable $slot_start, \DateTimeImmutable $slot_end): bool {
        $day_of_week = intval($slot_start->format('w'));
        $days_of_week = $this->extract_days_from_rate($rate);

        if (!empty($days_of_week) && !in_array($day_of_week, $days_of_week, true)) {
            return false;
        }

        $window_start = $this->normalize_time_value($rate['StartTime'] ?? null);
        $window_end = $this->normalize_time_value($rate['EndTime'] ?? null);
        if ($window_start === null && $window_end === null) {
            return true;
        }

        if ($window_start === null || $window_end === null) {
            return false;
        }

        $slot_start_time = $slot_start->format('H:i:s');
        $slot_end_time = $slot_end->format('H:i:s');

        return strcmp($slot_start_time, $window_start) >= 0
            && strcmp($slot_end_time, $window_end) <= 0;
    }

    private function normalize_day_of_week($value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $day = \intval($value);
        if ($day < 0 || $day > 6) {
            return null;
        }

        return $day;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int>|null
     */
    private function normalize_days_of_week(array $data): ?array {
        $raw_days = $data['days_of_week'] ?? null;

        if ($raw_days === null || $raw_days === '') {
            $legacy_day = trim((string) ($data['day_of_week'] ?? ''));
            $raw_days = $legacy_day !== '' ? [$legacy_day] : [];
        }

        if (!is_array($raw_days)) {
            $raw_days = [$raw_days];
        }

        $days = [];
        foreach ($raw_days as $raw_day) {
            $value = trim((string) $raw_day);
            if ($value === '') {
                continue;
            }

            $day = $this->normalize_day_of_week($value);
            if ($day === null) {
                return null;
            }

            $days[$day] = $day;
        }

        ksort($days);
        return array_values($days);
    }

    /**
     * @param array<string, mixed> $rate
     * @return array<int>
     */
    private function extract_days_from_rate(array $rate): array {
        $days = $rate['DaysOfWeek'] ?? [];
        if (!is_array($days)) {
            $days = [];
        }

        $normalized = [];
        foreach ($days as $raw_day) {
            $day = $this->normalize_day_of_week($raw_day);
            if ($day !== null) {
                $normalized[$day] = $day;
            }
        }

        if (empty($normalized)) {
            $legacy_day = $this->normalize_day_of_week($rate['DayOfWeek'] ?? null);
            if ($legacy_day !== null) {
                $normalized[$legacy_day] = $legacy_day;
            }
        }

        ksort($normalized);
        return array_values($normalized);
    }

    private function normalize_time_value($value): ?string {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $raw) === 1) {
            $raw .= ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $raw) !== 1) {
            return null;
        }

        return $raw;
    }

    private function create_datetime(string $date, string $time): ?\DateTimeImmutable {
        $value = trim($date . ' ' . $time);
        $date_time = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i', $value);

        return $date_time ?: null;
    }

    private function is_quarter_hour_time(string $value): bool {
        $parts = explode(':', $value);
        $minutes = isset($parts[1]) ? \intval($parts[1]) : -1;
        $seconds = isset($parts[2]) ? \intval($parts[2]) : 0;

        return $minutes >= 0 && $minutes < 60 && ($minutes % 15) === 0 && $seconds === 0;
    }
}