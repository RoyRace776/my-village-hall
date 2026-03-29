<?php
namespace MYVH\Organisations;

use MYVH\Core\Support\RequestMapperBase;

if (!defined('ABSPATH')) exit;

class AddOrgMemberRequest extends RequestMapperBase {
    public static function from_post(array $post): array {
        return [
            'organisation_id' => self::as_int($post, 'organisation_id'),
            'user_id'         => self::as_int($post, 'user_id'),
            'redirect'        => self::as_text($post, 'redirect'),
            'customer_id'     => self::as_int($post, 'customer_id'),
        ];
    }
}
