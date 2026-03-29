<?php
namespace MYVH\Organisations;

use MYVH\Core\Support\RequestMapperBase;

if (!defined('ABSPATH')) exit;

class RemoveOrgMemberRequest extends RequestMapperBase {
    public static function from_query(array $query): array {
        return [
            'id'              => self::as_int($query, 'id'),
            'organisation_id' => self::as_int($query, 'organisation_id'),
            'redirect'        => self::as_text($query, 'redirect'),
            'customer_id'     => self::as_int($query, 'customer_id'),
        ];
    }
}
