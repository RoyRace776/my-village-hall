<?php

if (!defined('ABSPATH')) exit;

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-validator-base.php';

class BookingRequestValidator extends RequestValidatorBase {

    /**
     * Lightweight input validation before booking service orchestration.
     *
     * @param array $data
     * @return true|WP_Error
     */
    public function validate(array $data): true|WP_Error {
        $required = $this->require_field($data, 'customer_id', __('Customer is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        $required = $this->require_field($data, 'room_id', __('Room is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        $required = $this->require_field($data, 'start_date', __('Start date is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        if (empty($data['start_time']) || empty($data['end_time'])) {
            return $this->validation_error(__('Start and end times are required', 'my-village-hall'));
        }

        if (!empty($data['booking_id']) && intval($data['booking_id']) < 0) {
            return $this->validation_error(__('Invalid booking id', 'my-village-hall'));
        }

        return true;
    }
}