<?php

if (!defined('ABSPATH')) exit;

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-mapper-base.php';

class SaveInvoiceRequest extends RequestMapperBase {

    public static function from_post(array $post): array {
        return [
            'invoice_id'       => self::as_int($post, 'invoice_id'),
            'invoice_number'   => self::as_text($post, 'invoice_number'),
            'customer_id'      => self::as_int($post, 'customer_id'),
            'booking_id'       => self::as_int($post, 'booking_id'),
            'invoice_date'     => self::as_text($post, 'invoice_date'),
            'due_date'         => self::as_text($post, 'due_date'),
            'sub_total'        => self::as_float($post, 'sub_total'),
            'tax_amount'       => self::as_float($post, 'tax_amount'),
            'discount_amount'  => self::as_float($post, 'discount_amount'),
            'total_amount'     => self::as_float($post, 'total_amount'),
            'amount_paid'      => self::as_float($post, 'amount_paid'),
            'status'           => self::as_text($post, 'status', 'draft'),
            'notes'            => self::as_textarea($post, 'notes'),
        ];
    }
}