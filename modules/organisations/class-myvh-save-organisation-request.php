<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MYVH_PLUGIN_DIR . 'core/support/class-myvh-request-mapper-base.php';

class MYVH_Save_Organisation_Request extends MYVH_Request_Mapper_Base {

    public static function from_post(array $post): array {
        return [
            'organisation_id'      => self::as_int($post, 'organisation_id'),
            'name'                 => self::as_text($post, 'name'),
            'organisation_type_id' => self::as_int($post, 'organisation_type_id'),
            'contact_email'        => self::as_email($post, 'contact_email'),
            'contact_phone'        => self::as_text($post, 'contact_phone'),
            'website_url'          => !empty($post['website_url']) ? esc_url_raw($post['website_url']) : null,
            'is_active'            => self::as_bool_int($post, 'is_active'),
            'is_default'           => self::as_bool_int($post, 'is_default'),
            'default_public'       => self::as_bool_int($post, 'default_public'),
        ];
    }
}