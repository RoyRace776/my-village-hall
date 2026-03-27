<?php

if (!defined('ABSPATH')) exit;

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-validator-base.php';

class VenueRequestValidator extends RequestValidatorBase {

    public function validate(array $data): true|WP_Error {
        $required = $this->require_field($data, 'name', __('Venue name is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        return true;
    }
}