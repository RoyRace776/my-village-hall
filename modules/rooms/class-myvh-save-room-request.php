<?php

if (!defined('ABSPATH')) exit;

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-mapper-base.php';

class Save_Room_Request extends Request_Mapper_Base {

    public static function from_post(array $post): array {
        return [
            'room_id'                  => self::as_int($post, 'room_id'),
            'name'                     => self::as_text($post, 'name'),
            'venue_id'                 => self::as_int($post, 'venue_id'),
            'capacity'                 => self::as_int($post, 'capacity'),
            'description'              => self::as_textarea($post, 'description'),
            'opening_time'             => self::as_text($post, 'opening_time'),
            'closing_time'             => self::as_text($post, 'closing_time'),
            'allow-multi-day-bookings' => self::as_bool_int($post, 'allow-multi-day-bookings'),
            'calc-closed-hours'        => self::as_bool_int($post, 'calc-closed-hours'),
        ];
    }
}