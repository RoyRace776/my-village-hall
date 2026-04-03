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
        ];
    }
}