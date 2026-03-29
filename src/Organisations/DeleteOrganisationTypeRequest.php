<?php
namespace MYVH\Organisations;

use MYVH\Core\Support\RequestMapperBase;

if (!defined('ABSPATH')) exit;

class DeleteOrganisationTypeRequest extends RequestMapperBase {
    public static function from_query(array $query): array {
        return [
            'id' => self::as_int($query, 'id'),
        ];
    }
}
