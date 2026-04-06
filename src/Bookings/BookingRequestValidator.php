<?php

namespace MYVH\Bookings;

use MYVH\Core\Support\RequestValidatorBase;
use WP_Error;

if (!defined('ABSPATH')) exit;

class BookingRequestValidator extends RequestValidatorBase
{
    private const ALLOWED_EDIT_SCOPES = [
        'this_only',
        'all_bookings',
        'this_and_future',
    ];

    /**
     * Lightweight input validation before booking service orchestration.
     *
     * @param array $data
     * @return true|WP_Error
     */
    public function validate(array $data): bool|WP_Error
    {
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

        if (!empty($data['edit_scope']) && !in_array($data['edit_scope'], self::ALLOWED_EDIT_SCOPES, true)) {
            return $this->validation_error(__('Invalid recurring update scope', 'my-village-hall'));
        }

        return true;
    }
}
