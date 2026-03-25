<?php

if (!defined('ABSPATH')) exit;

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-validator-base.php';

class MYVH_Room_Rate_Request_Validator extends MYVH_Request_Validator_Base {

    public function validate(array $data): true|WP_Error {
        $required = $this->require_field($data, 'room_id', __('Room is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        $required = $this->require_field($data, 'name', __('Rate name is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        $charge_type = $data['charge_type'] ?? '';
        $in_list = $this->require_in_list($charge_type, ['per_hour', 'per_day', 'fixed'], __('Invalid charge type', 'my-village-hall'));
        if (is_wp_error($in_list)) return $in_list;

        $non_negative = $this->require_non_negative($data, 'rate', __('Rate must be zero or greater', 'my-village-hall'));
        if (is_wp_error($non_negative)) return $non_negative;

        return true;
    }
}