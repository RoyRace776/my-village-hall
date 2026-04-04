<?php
namespace MYVH\Organisations;

use MYVH\Core\Support\RequestMapperBase;

if (!defined('ABSPATH')) exit;

class SaveOrganisationTypeRequest extends RequestMapperBase {
    public static function from_post(array $post): array {
        return [
            'org_type_id'  => self::as_int($post, 'org_type_id'),
            'name'         => self::as_text($post, 'name'),
            'description'  => self::as_textarea($post, 'description'),
            'is_default'   => self::as_bool_int($post, 'is_default'),
        ];
    }
}
