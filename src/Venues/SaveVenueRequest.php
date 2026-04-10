<?php
namespace MYVH\Venues;

use MYVH\Core\Support\RequestMapperBase;

if (!defined('ABSPATH')) exit;

class SaveVenueRequest extends RequestMapperBase {

    public static function from_post(array $post): array {
        return [
            'venue_id'     => self::as_int($post, 'venue_id'),
            'name'         => self::as_text($post, 'name'),
            'short_name'   => self::as_text($post, 'short_name'),
            'post_code'    => self::as_text($post, 'post_code'),
            'address_line1'=> self::as_text($post, 'address_line1'),
            'opening_time' => self::as_text($post, 'opening_time'),
            'closing_time' => self::as_text($post, 'closing_time'),
            'opening_hours_by_day' => self::parse_opening_hours_by_day($post),
        ];
    }

    private static function parse_opening_hours_by_day(array $post): array {
        $source = $post['opening_hours_by_day'] ?? ($post['venue_hours'] ?? []);

        if (is_string($source) && $source !== '') {
            $decoded = json_decode(wp_unslash($source), true);
            if (is_array($decoded)) {
                $source = $decoded;
            }
        }

        if (!is_array($source)) {
            return [];
        }

        $rows = [];
        foreach ($source as $day => $row) {
            if (!is_array($row)) {
                continue;
            }

            $day_of_week = isset($row['day_of_week']) ? intval($row['day_of_week']) : intval($day);
            if ($day_of_week < 0 || $day_of_week > 6) {
                continue;
            }

            $is_closed = !empty($row['is_closed']) ? 1 : 0;
            $opening_time = sanitize_text_field($row['opening_time'] ?? '');
            $closing_time = sanitize_text_field($row['closing_time'] ?? '');

            if ($is_closed) {
                $opening_time = '';
                $closing_time = '';
            }

            $rows[$day_of_week] = [
                'day_of_week' => $day_of_week,
                'is_closed' => $is_closed,
                'opening_time' => $opening_time,
                'closing_time' => $closing_time,
            ];
        }

        ksort($rows);
        return array_values($rows);
    }
}