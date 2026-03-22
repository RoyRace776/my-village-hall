<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-mapper-base.php';

class MYVH_Delete_Organisation_Type_Request extends MYVH_Request_Mapper_Base {

    public static function from_query(array $query): array {
        return [
            'id' => self::as_int($query, 'id'),
        ];
    }
}