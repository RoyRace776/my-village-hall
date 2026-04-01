<?php
namespace MYVH\Customers;
use MYVH\Core\Support\RequestMapperBase;

if (!defined('ABSPATH')) exit;

class SaveCustomerRequest extends RequestMapperBase {

    public static function from_post(array $post): array {
        return [
            'customer_id'        => self::as_int($post, 'customer_id'),
            'user_id'            => self::as_int($post, 'user_id'),
            'name'               => self::as_text($post, 'name'),
            'email'              => self::as_email($post, 'email'),
            'phone_number'       => self::as_text($post, 'phone_number'),
            'post_code'          => self::as_text($post, 'post_code'),
            'address_line1'      => self::as_text($post, 'address_line1'),
            'email_verified'     => self::as_bool_int($post, 'email_verified'),
            'allow_auto_confirm' => self::as_bool_int($post, 'allow_auto_confirm'),
        ];
    }
}
