<?php

if (!defined('ABSPATH')) exit;

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-validator-base.php';

class Invoice_Request_Validator extends Request_Validator_Base {

    public function validate(array $data): true|WP_Error {
        $required = $this->require_field($data, 'customer_id', __('Customer is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        $non_negative = $this->require_non_negative($data, 'total_amount', __('Valid total amount is required', 'my-village-hall'));
        if (is_wp_error($non_negative)) return $non_negative;

        return true;
    }
}