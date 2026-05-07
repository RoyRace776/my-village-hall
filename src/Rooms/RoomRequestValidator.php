<?php
namespace MYVH\Rooms;

use MYVH\Core\Support\RequestValidatorBase;

if (!defined('ABSPATH')) exit;

class RoomRequestValidator extends RequestValidatorBase {

    public function validate(array $data): true|\WP_Error {
        $required = $this->require_field($data, 'name', __('Room name is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        $required = $this->require_field($data, 'room_colour', __('Room colour is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        if (!RoomColour::is_valid($data['room_colour'] ?? '')) {
            return $this->validation_error(__('Room colour must be a valid hex value such as #4f7cac', 'my-village-hall'));
        }

        $required = $this->require_field($data, 'venue_id', __('Venue is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        if (empty($data['opening_time']) || empty($data['closing_time'])) {
            return $this->validation_error(__('Opening and closing times are required', 'my-village-hall'));
        }

        $valid_days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        foreach ((array) ($data['deposit_days'] ?? []) as $day) {
            if (!in_array((string) $day, $valid_days, true)) {
                return $this->validation_error(__('Deposit days contain an invalid value', 'my-village-hall'));
            }
        }

        $deposit_end_after = $data['deposit_end_after'] ?? null;
        if ($deposit_end_after !== null && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $deposit_end_after)) {
            return $this->validation_error(__('Deposit end time must be in HH:MM format', 'my-village-hall'));
        }

        if (isset($data['deposit_amount']) && (float) $data['deposit_amount'] < 0) {
            return $this->validation_error(__('Deposit amount cannot be negative', 'my-village-hall'));
        }

        $deposit_action = (string) ($data['deposit_action'] ?? 'auto_add');
        if (!in_array($deposit_action, ['auto_add', 'require_review'], true)) {
            return $this->validation_error(__('Deposit action is invalid', 'my-village-hall'));
        }

        return true;
    }
}