<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-mapper-base.php';

class MYVH_Save_Organisation_Type_Request extends MYVH_Request_Mapper_Base {

    public static function from_post(array $post): array {
        return [
            'org_type_id'  => self::as_int($post, 'org_type_id'),
            'name'         => self::as_text($post, 'name'),
            'description'  => self::as_textarea($post, 'description'),
        ];
    }
}