<?php

if (!defined('ABSPATH')) exit;

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-validator-base.php';

class MYVH_Room_Request_Validator extends MYVH_Request_Validator_Base {

    public function validate(array $data) {
        $required = $this->require_field($data, 'name', __('Room name is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        $required = $this->require_field($data, 'venue_id', __('Venue is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        if (empty($data['opening_time']) || empty($data['closing_time'])) {
            return $this->validation_error(__('Opening and closing times are required', 'my-village-hall'));
        }

        return true;
    }
}