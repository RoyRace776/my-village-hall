<?php

if (!defined('ABSPATH')) exit;

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-validator-base.php';

class MYVH_Customer_Request_Validator extends MYVH_Request_Validator_Base {

    public function validate(array $data): true|WP_Error {
        $required = $this->require_field($data, 'name', __('Customer name is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        $email = $this->require_email(
            $data,
            'email',
            __('Email is required', 'my-village-hall'),
            __('Valid email address is required', 'my-village-hall')
        );
        if (is_wp_error($email)) return $email;

        return true;
    }
}