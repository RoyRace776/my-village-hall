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

        return true;
    }
}