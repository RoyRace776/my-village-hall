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
            'billing_contact_name' => self::as_text($post, 'billing_contact_name'),
            'billing_email'        => self::as_email($post, 'billing_email'),
            'billing_address_line1'=> self::as_text($post, 'billing_address_line1'),
            'billing_address_line2'=> self::as_text($post, 'billing_address_line2'),
            'billing_town_city'    => self::as_text($post, 'billing_town_city'),
            'billing_postcode'     => self::as_text($post, 'billing_postcode'),
            'billing_reference'    => self::as_text($post, 'billing_reference'),
            'is_active'            => self::as_bool_int($post, 'is_active'),
            'is_default'           => self::as_bool_int($post, 'is_default'),
            'default_public'       => self::as_bool_int($post, 'default_public'),
        ];
    }
}