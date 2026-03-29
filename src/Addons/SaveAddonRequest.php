<?php
namespace MYVH\Addons;

use MYVH\Core\Support\RequestMapperBase;

if (!defined('ABSPATH')) exit;

class SaveAddonRequest extends RequestMapperBase {

    public static function from_post(array $post): array {
        return [
            'addon_id'      => self::as_int($post, 'addon_id'),
            'name'          => self::as_text($post, 'name'),
            'description'   => self::as_textarea($post, 'description'),
            'price'         => self::as_float($post, 'price'),
            'charge_type'   => self::as_text($post, 'charge_type'),
            'room_id'       => self::as_int($post, 'room_id'),
            'is_active'     => self::as_bool_int($post, 'is_active'),
            'display_order' => self::as_int($post, 'display_order'),
        ];
    }
}