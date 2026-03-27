<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-mapper-base.php';

class Add_Org_Member_Request extends Request_Mapper_Base {

    public static function from_post(array $post): array {
        return [
            'organisation_id' => self::as_int($post, 'organisation_id'),
            'user_id'         => self::as_int($post, 'user_id'),
            'redirect'        => self::as_text($post, 'redirect'),
            'customer_id'     => self::as_int($post, 'customer_id'),
        ];
    }
}