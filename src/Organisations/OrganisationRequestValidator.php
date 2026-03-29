<?php
namespace MYVH\Organisations;

use MYVH\Core\Support\RequestValidatorBase;
use WP_Error;

if (!defined('ABSPATH')) exit;

class OrganisationRequestValidator extends RequestValidatorBase {
    public function validate(array $data): true|WP_Error {
        $required = $this->require_field($data, 'name', __('Organisation name is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;
        $email = $this->require_email(
            $data,
            'contact_email',
            __('Contact email is required', 'my-village-hall'),
            __('Contact email must be a valid email address', 'my-village-hall')
        );
        if (is_wp_error($email)) return $email;
        $required = $this->require_field($data, 'contact_phone', __('Contact phone is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;
        return true;
    }
    public function validate_add_member(array $data): true|WP_Error {
        $required = $this->require_field($data, 'organisation_id', __('Organisation is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;
        $required = $this->require_field($data, 'user_id', __('Customer is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;
        return true;
    }
    public function validate_remove_member(array $data): true|WP_Error {
        $required = $this->require_field($data, 'id', __('Organisation member is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;
        return true;
    }
    public function validate_delete(array $data): true|WP_Error {
        $required = $this->require_field($data, 'id', __('Organisation is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;
        return true;
    }
}
