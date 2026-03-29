<?php
namespace MYVH\Organisations;

use MYVH\Core\Support\RequestValidatorBase;
use WP_Error;

if (!defined('ABSPATH')) exit;

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
