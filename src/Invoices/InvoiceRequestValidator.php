<?php
namespace MYVH\Invoices;

use MYVH\Core\Support\RequestValidatorBase;

use WP_Error;

if (!defined('ABSPATH')) exit;

require_once MYVH_PLUGIN_DIR . 'core/support/RequestValidatorBase.php';

class InvoiceRequestValidator extends RequestValidatorBase {

    public function validate(array $data): true|WP_Error {
        $required = $this->require_field($data, 'customer_id', __('Customer is required', 'my-village-hall'));
        if (is_wp_error($required)) return $required;

        $non_negative = $this->require_non_negative($data, 'total_amount', __('Valid total amount is required', 'my-village-hall'));
        if (is_wp_error($non_negative)) return $non_negative;

        return true;
    }
}