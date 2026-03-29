<?php
namespace MYVH\Addons;

use MYVH\Core\Support\RequestValidatorBase;

if (!defined('ABSPATH')) exit;

class AddonRequestValidator extends RequestValidatorBase {

    public function validate(array $data): bool|\WP_Error {
        $required = $this->require_field($data, 'name', __('Add-on name is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        $non_negative = $this->require_non_negative($data, 'price', __('Valid price is required', 'my-village-hall'));
        if (is_wp_error($non_negative)) return $non_negative;

        $required = $this->require_field($data, 'charge_type', __('Charge type is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        return true;
    }
}