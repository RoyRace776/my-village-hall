<?php
namespace MYVH\Pricing;

use MYVH\Core\Support\RequestMapperBase;

if (!defined('ABSPATH')) exit;

class SaveRoomRateRequest extends RequestMapperBase {

    private static function as_days_of_week(array $post): array {
        $raw_days = $post['days_of_week'] ?? [];
        if (!is_array($raw_days)) {
            $raw_days = [$raw_days];
        }

        $days = [];
        foreach ($raw_days as $raw_day) {
            $day = trim((string) $raw_day);
            if ($day !== '') {
                $days[] = $day;
            }
        }

        if (empty($days)) {
            $legacy_day = self::as_text($post, 'day_of_week');
            if ($legacy_day !== '') {
                $days[] = $legacy_day;
            }
        }

        return $days;
    }

    public static function from_post(array $post): array {
        return [
            'rate_id'               => self::as_int($post, 'rate_id'),
            'room_id'               => self::as_int($post, 'room_id'),
            'organisation_type_id'  => self::as_int($post, 'organisation_type_id'),
            'days_of_week'          => self::as_days_of_week($post),
            'day_of_week'           => self::as_text($post, 'day_of_week'),
            'start_time'            => self::as_text($post, 'start_time'),
            'end_time'              => self::as_text($post, 'end_time'),
            'charge_type'           => self::as_text($post, 'charge_type'),
            'rate'                  => self::as_float($post, 'rate'),
            'name'                  => self::as_text($post, 'name'),
            'description'           => self::as_textarea($post, 'description'),
            'minimum_hours'         => self::as_float($post, 'minimum_hours'),
            'is_active'             => self::as_bool_int($post, 'is_active'),
            'valid_from'            => self::as_text($post, 'valid_from'),
            'valid_to'              => self::as_text($post, 'valid_to'),
            'priority'              => self::as_int($post, 'priority'),
        ];
    }
}