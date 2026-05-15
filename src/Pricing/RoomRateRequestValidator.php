<?php
namespace MYVH\Pricing;
use MYVH\Core\Support\RequestValidatorBase;

if (!defined('ABSPATH')) exit;

class RoomRateRequestValidator extends RequestValidatorBase {

    public function validate(array $data): bool|\WP_Error {
        $required = $this->require_field($data, 'room_id', __('Room is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        $required = $this->require_field($data, 'name', __('Rate name is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        $charge_type = $data['charge_type'] ?? '';
        $in_list = $this->require_in_list($charge_type, ['per_hour', 'per_day', 'fixed'], __('Invalid charge type', 'my-village-hall'));
        if (is_wp_error($in_list)) return $in_list;

        $non_negative = $this->require_non_negative($data, 'rate', __('Rate must be zero or greater', 'my-village-hall'));
        if (is_wp_error($non_negative)) return $non_negative;

        if (!isset($data['minimum_hours']) || \floatval($data['minimum_hours']) <= 0) {
            return $this->validation_error(__('Minimum hours must be greater than zero', 'my-village-hall'));
        }

        $day_of_week_raw = trim((string) ($data['day_of_week'] ?? ''));
        if ($day_of_week_raw !== '') {
            if (!ctype_digit($day_of_week_raw)) {
                return $this->validation_error(__('Day of week must be between 0 and 6', 'my-village-hall'));
            }

            $day_of_week = \intval($day_of_week_raw);
            if ($day_of_week < 0 || $day_of_week > 6) {
                return $this->validation_error(__('Day of week must be between 0 and 6', 'my-village-hall'));
            }
        }

        $start_time_raw = trim((string) ($data['start_time'] ?? ''));
        $end_time_raw = trim((string) ($data['end_time'] ?? ''));

        if (($start_time_raw === '') xor ($end_time_raw === '')) {
            return $this->validation_error(__('Start and end time must be provided together', 'my-village-hall'));
        }

        $start_time = $this->normalize_time($start_time_raw);
        $end_time = $this->normalize_time($end_time_raw);

        if ($start_time_raw !== '' && $start_time === null) {
            return $this->validation_error(__('Start time must use HH:MM or HH:MM:SS format', 'my-village-hall'));
        }

        if ($start_time !== null && !$this->is_quarter_hour_time($start_time)) {
            return $this->validation_error(__('Start time must use 15 minute intervals', 'my-village-hall'));
        }

        if ($end_time_raw !== '' && $end_time === null) {
            return $this->validation_error(__('End time must use HH:MM or HH:MM:SS format', 'my-village-hall'));
        }

        if ($end_time !== null && !$this->is_quarter_hour_time($end_time)) {
            return $this->validation_error(__('End time must use 15 minute intervals', 'my-village-hall'));
        }

        if ($start_time !== null && $end_time !== null && strcmp($end_time, $start_time) <= 0) {
            return $this->validation_error(__('End time must be later than start time', 'my-village-hall'));
        }

        $has_schedule_fields = $day_of_week_raw !== '' || $start_time !== null || $end_time !== null;
        if ($has_schedule_fields && $charge_type === 'fixed') {
            return $this->validation_error(__('Fixed rates cannot be limited to a day/time schedule', 'my-village-hall'));
        }

        return true;
    }

    private function normalize_time(string $value): ?string {
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        return null;
    }

    private function is_quarter_hour_time(string $value): bool {
        $parts = explode(':', $value);
        $minutes = isset($parts[1]) ? \intval($parts[1]) : -1;
        $seconds = isset($parts[2]) ? \intval($parts[2]) : 0;

        return $minutes >= 0 && $minutes < 60 && ($minutes % 15) === 0 && $seconds === 0;
    }
}