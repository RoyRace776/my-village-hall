<?php

if (!defined('ABSPATH')) exit;

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-mapper-base.php';

class Save_Venue_Request extends Request_Mapper_Base {

    public static function from_post(array $post): array {
        return [
            'venue_id'     => self::as_int($post, 'venue_id'),
            'name'         => self::as_text($post, 'name'),
            'short_name'   => self::as_text($post, 'short_name'),
            'post_code'    => self::as_text($post, 'post_code'),
            'address_line1'=> self::as_text($post, 'address_line1'),
            'opening_time' => self::as_text($post, 'opening_time'),
            'closing_time' => self::as_text($post, 'closing_time'),
        ];
    }
}