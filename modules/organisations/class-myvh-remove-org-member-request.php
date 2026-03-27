<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-mapper-base.php';

class Remove_Org_Member_Request extends Request_Mapper_Base {

    public static function from_query(array $query): array {
        return [
            'id'              => self::as_int($query, 'id'),
            'organisation_id' => self::as_int($query, 'organisation_id'),
            'redirect'        => self::as_text($query, 'redirect'),
            'customer_id'     => self::as_int($query, 'customer_id'),
        ];
    }
}