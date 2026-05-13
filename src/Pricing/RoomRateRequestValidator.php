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

        if (!isset($data['minimum_hours']) || floatval($data['minimum_hours']) <= 0) {
            return $this->validation_error(__('Minimum hours must be greater than zero', 'my-village-hall'));
        }

        return true;
    }
}