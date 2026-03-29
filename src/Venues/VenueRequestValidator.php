<?php
namespace MYVH\Venues;

use MYVH\Core\Support\RequestValidatorBase;
use WP_Error;

if (!defined('ABSPATH')) exit;

class VenueRequestValidator extends RequestValidatorBase {

    public function validate(array $data): true|WP_Error {
        $required = $this->require_field($data, 'name', __('Venue name is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        return true;
    }
}