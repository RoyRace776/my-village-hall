<?php
namespace MYVH\Organisations;

use MYVH\Core\Support\RequestMapperBase;

if (!defined('ABSPATH')) exit;

class SaveOrganisationRequest extends RequestMapperBase {
    public static function from_post(array $post, bool $allow_restricted_fields = true): array {
        $base = [
            'organisation_id'               => self::as_int($post, 'organisation_id'),
            'name'                          => self::as_text($post, 'name'),
            'contact_email'                 => self::as_email($post, 'contact_email'),
            'contact_phone'                 => self::as_text($post, 'contact_phone'),
            'website_url'                   => !empty($post['website_url']) ? esc_url_raw($post['website_url']) : null,
            'invoice_organisation_bookings' => self::as_bool_int($post, 'invoice_organisation_bookings'),
            'send_booking_emails_to_organisation' => self::as_bool_int($post, 'send_booking_emails_to_organisation'),
            'single_booking_auto_invoice_rule_id' => self::as_int($post, 'single_booking_auto_invoice_rule_id'),
            'recurring_booking_auto_invoice_rule_id' => self::as_int($post, 'recurring_booking_auto_invoice_rule_id'),
            'billing_contact_name'          => self::as_text($post, 'billing_contact_name'),
            'billing_email'                 => self::as_email($post, 'billing_email'),
            'billing_address_line1'         => self::as_text($post, 'billing_address_line1'),
            'billing_address_line2'         => self::as_text($post, 'billing_address_line2'),
            'billing_town_city'             => self::as_text($post, 'billing_town_city'),
            'billing_postcode'              => self::as_text($post, 'billing_postcode'),
            'billing_reference'             => self::as_text($post, 'billing_reference'),
        ];

        if ($allow_restricted_fields) {
            $base['organisation_type_id'] = self::as_int($post, 'organisation_type_id');
            $base['is_active'] = self::as_bool_int($post, 'is_active');
            $base['is_default'] = self::as_bool_int($post, 'is_default');
            $base['default_public'] = self::as_bool_int($post, 'default_public');
        }

        return $base;
    }
}
