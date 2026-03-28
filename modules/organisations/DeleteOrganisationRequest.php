<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-mapper-base.php';

class DeleteOrganisationRequest extends RequestMapperBase {

    public static function from_query(array $query): array {
        return [
            'id' => self::as_int($query, 'id'),
        ];
    }
}