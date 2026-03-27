<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-validator-base.php';

class OrganisationTypeRequestValidator extends RequestValidatorBase {

    public function validate(array $data) {
        $required = $this->require_field($data, 'name', __('Organisation type name is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        return true;
    }

    public function validate_delete(array $data) {
        $required = $this->require_field($data, 'id', __('Organisation type is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        return true;
    }
}