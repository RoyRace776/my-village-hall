<?php
namespace MYVH\Pricing;

use MYVH\Core\Support\RequestMapperBase;

if (!defined('ABSPATH')) exit;

class SaveRoomRateRequest extends RequestMapperBase {

    public static function from_post(array $post): array {
        return [
            'rate_id'               => self::as_int($post, 'rate_id'),
            'room_id'               => self::as_int($post, 'room_id'),
            'organisation_type_id'  => self::as_int($post, 'organisation_type_id'),
            'charge_type'           => self::as_text($post, 'charge_type'),
            'rate'                  => self::as_float($post, 'rate'),
            'name'                  => self::as_text($post, 'name'),
            'description'           => self::as_textarea($post, 'description'),
            'minimum_hours'         => self::as_float($post, 'minimum_hours'),
            'is_active'             => self::as_bool_int($post, 'is_active'),
            'valid_from'            => self::as_text($post, 'valid_from'),
            'valid_to'              => self::as_text($post, 'valid_to'),
            'priority'              => self::as_int($post, 'priority'),
        ];
    }
}