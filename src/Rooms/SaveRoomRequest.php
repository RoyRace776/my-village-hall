<?php
namespace MYVH\Rooms;

use MYVH\Core\Support\RequestMapperBase;

if (!defined('ABSPATH')) exit;

class SaveRoomRequest extends RequestMapperBase {

    public static function from_post(array $post): array {
        $room_colour = self::as_text($post, 'room_colour');
        if ($room_colour === '') {
            $room_colour = self::as_text($post, 'room_color');
        }

        return [
            'room_id'                  => self::as_int($post, 'room_id'),
            'name'                     => self::as_text($post, 'name'),
            'room_colour'              => $room_colour,
            'venue_id'                 => self::as_int($post, 'venue_id'),
            'capacity'                 => self::as_int($post, 'capacity'),
            'description'              => self::as_textarea($post, 'description'),
            'opening_time'             => self::as_text($post, 'opening_time'),
            'closing_time'             => self::as_text($post, 'closing_time'),
            'allow-multi-day-bookings' => self::as_bool_int($post, 'allow-multi-day-bookings'),
            'calc-closed-hours'        => self::as_bool_int($post, 'calc-closed-hours'),
            'is-public'                => self::as_bool_int($post, 'is-public'),
            'opening_hours_by_day'     => self::parse_opening_hours_by_day($post),
        ];
    }

    private static function parse_opening_hours_by_day(array $post): array {
        $source = $post['opening_hours_by_day'] ?? ($post['room_hours'] ?? []);

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

            $use_venue_hours = !empty($row['use_venue_hours']) ? 1 : 0;
            $is_closed = !empty($row['is_closed']) ? 1 : 0;
            $opening_time = sanitize_text_field($row['opening_time'] ?? '');
            $closing_time = sanitize_text_field($row['closing_time'] ?? '');

            if ($use_venue_hours) {
                $is_closed = 0;
                $opening_time = '';
                $closing_time = '';
            } elseif ($is_closed) {
                $opening_time = '';
                $closing_time = '';
            }

            $rows[$day_of_week] = [
                'day_of_week' => $day_of_week,
                'use_venue_hours' => $use_venue_hours,
                'is_closed' => $is_closed,
                'opening_time' => $opening_time,
                'closing_time' => $closing_time,
            ];
        }

        ksort($rows);
        return array_values($rows);
    }
}