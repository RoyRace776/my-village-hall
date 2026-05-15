<?php

namespace MYVH\Bookings;

use MYVH\Core\Support\RequestMapperBase;

if (!defined('ABSPATH')) exit;

class SaveBookingRequest extends RequestMapperBase
{
    /**
     * Normalize the booking save payload into a service-friendly shape.
     *
     * @param array $post
     * @return array
     */
    public static function from_post(array $post): array
    {
        $data = [
            'booking_id'         => self::as_int($post, 'booking_id'),
            'edit_scope'         => self::as_text($post, 'edit_scope'),
            'customer_id'        => self::as_int($post, 'customer_id'),
            'organisation_id'    => self::as_int($post, 'organisation_id'),
            'room_id'            => self::as_int($post, 'room_id'),
            'status'             => self::as_text($post, 'status'),
            'start_date'         => self::as_text($post, 'start_date'),
            'end_date'           => self::as_text($post, 'end_date'),
            'start_time'         => self::as_text($post, 'start_time'),
            'end_time'           => self::as_text($post, 'end_time'),
            'description'        => self::as_textarea($post, 'description'),
            'public'             => self::as_bool_int($post, 'public'),
            'no_invoice_required'=> self::as_bool_int($post, 'no_invoice_required'),
            'return_to'          => self::as_redirect($post, 'return_to', ''),
            'chargeable_hours'   => array_key_exists('chargeable_hours', $post) ? self::as_float($post, 'chargeable_hours') : null,
            'is_recurring'       => self::as_bool_int($post, 'is_recurring'),
            'recurrence_type'    => self::as_text($post, 'recurrence_type'),
            'recurrence_interval'=> self::as_int($post, 'recurrence_interval', 1),
            'recurrence_day'     => self::as_text($post, 'recurrence_day'),
            'recurrence_week'    => self::as_text($post, 'recurrence_week'),
            'recurrence_end_type'=> self::as_text($post, 'recurrence_end_type'),
            'recurrence_end_date'=> self::as_text($post, 'recurrence_end_date'),
            'max_occurrences'    => self::as_int($post, 'max_occurrences'),
            'addons'             => self::normalize_addons($post['addons'] ?? []),
        ];

        if ($data['chargeable_hours'] === null) {
            unset($data['chargeable_hours']);
        }

        return $data;
    }

    private static function normalize_addons($addons): array
    {
        if (empty($addons) || !is_array($addons)) {
            return [];
        }

        $normalized = [];

        foreach ($addons as $addon) {
            $enabled = !empty($addon['enabled']) && strval($addon['enabled']) === '1';
            $addon_id = \intval($addon['addon_id'] ?? 0);

            if (!$enabled || $addon_id <= 0) {
                continue;
            }

            $normalized[] = [
                'addon_id'    => $addon_id,
                'quantity'    => self::as_float($addon, 'quantity', 1),
                'unit_price'  => self::as_float($addon, 'unit_price'),
                'description' => self::as_text($addon, 'description'),
            ];
        }

        return $normalized;
    }
}
