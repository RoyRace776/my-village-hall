<?php
namespace MYVH\Portal\Actions;

use MYVH\Pricing\PricingService;
use WP_Error;

class TestRoomRateScheduleAction {
    public function __construct(
        private PricingService $pricing_service
    ) {}

    public function execute(array $request): array|WP_Error {
        $room_id = (int) ($request['room_id'] ?? 0);
        $organisation_type_id = (int) ($request['organisation_type_id'] ?? 0);

        $start_date = sanitize_text_field((string) ($request['start_date'] ?? ''));
        $start_time = sanitize_text_field((string) ($request['start_time'] ?? ''));
        $end_date = sanitize_text_field((string) ($request['end_date'] ?? ''));
        $end_time = sanitize_text_field((string) ($request['end_time'] ?? ''));

        if ($start_date === '' || $start_time === '') {
            [$start_date, $start_time] = $this->split_datetime((string) ($request['start_datetime'] ?? ''));
        }

        if ($end_date === '' || $end_time === '') {
            [$end_date, $end_time] = $this->split_datetime((string) ($request['end_datetime'] ?? ''), $start_date);
        }

        if ($room_id <= 0) {
            return new WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        if ($start_date === '' || $start_time === '' || $end_date === '' || $end_time === '') {
            return new WP_Error('validation', __('Start and end date/time are required', 'my-village-hall'));
        }

        $start_stamp = strtotime($start_date . ' ' . $start_time);
        $end_stamp = strtotime($end_date . ' ' . $end_time);
        if ($start_stamp === false || $end_stamp === false || $end_stamp <= $start_stamp) {
            return new WP_Error('validation', __('End time must be later than the start time', 'my-village-hall'));
        }

        if (!$this->is_quarter_hour_time($start_time)) {
            return new WP_Error('validation', __('Start time must use 15 minute intervals', 'my-village-hall'));
        }

        if (!$this->is_quarter_hour_time($end_time)) {
            return new WP_Error('validation', __('End time must use 15 minute intervals', 'my-village-hall'));
        }

        $result = $this->pricing_service->test_room_rate_schedule(
            $room_id,
            $organisation_type_id,
            $start_date,
            $start_time,
            $end_date,
            $end_time
        );
        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'room_id' => $room_id,
            'organisation_type_id' => $organisation_type_id,
            'start_date' => $start_date,
            'start_time' => $start_time,
            'end_date' => $end_date,
            'end_time' => $end_time,
            'charge' => $result,
        ];
    }

    private function split_datetime(string $value, string $default_date = ''): array {
        $raw = trim($value);
        if ($raw === '') {
            return [sanitize_text_field($default_date), ''];
        }

        $timestamp = strtotime($raw);
        if ($timestamp !== false) {
            return [date('Y-m-d', $timestamp), date('H:i:s', $timestamp)];
        }

        $parts = preg_split('/[T\s]/', $raw);
        $date = sanitize_text_field($parts[0] ?? $default_date);
        $time = sanitize_text_field($parts[1] ?? '');
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }

        return [$date, $time];
    }

    private function is_quarter_hour_time(string $value): bool {
        $normalized = trim($value);
        if (preg_match('/^\d{2}:\d{2}$/', $normalized) === 1) {
            $normalized .= ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $normalized) !== 1) {
            return false;
        }

        $parts = explode(':', $normalized);
        $minutes = \intval($parts[1] ?? -1);
        $seconds = \intval($parts[2] ?? -1);

        return $minutes >= 0 && $minutes < 60 && ($minutes % 15) === 0 && $seconds === 0;
    }
}
