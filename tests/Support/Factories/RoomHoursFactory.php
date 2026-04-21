<?php

namespace Tests\Support\Factories;

use MYVH\Rooms\RoomHoursRepository;
use RuntimeException;

class RoomHoursFactory
{
    /**
     * Returns a single room-hours row (not persisted).
     * day_of_week: 0=Sunday, 1=Monday ... 6=Saturday
     */
    public static function make(array $overrides = []): array
    {
        return array_merge([
            'Id'            => 0,
            'RoomId'        => 1,
            'DayOfWeek'     => 1,
            'UseVenueHours' => '0',
            'IsClosed'      => '0',
            'OpeningTime'   => '09:00:00',
            'ClosingTime'   => '17:00:00',
        ], $overrides);
    }

    /**
     * Persists a full week of room hours and returns the saved rows.
     *
     * Pass `room_id` to target an existing room, or let the factory create one.
     * Pass `rows` (snake_case keys matching replace_for_room()) to override the
     * default Mon–Fri 09:00–17:00 schedule.
     *
     * @return array  Array of saved row arrays, one per persisted day.
     */
    public static function create(array $overrides = []): array
    {
        $room    = isset($overrides['room_id']) ? null : RoomFactory::create();
        $room_id = (int) ($overrides['room_id'] ?? $room['Id']);
        $rows    = $overrides['rows'] ?? self::default_week_rows();

        $repo = app(RoomHoursRepository::class);
        $ok   = $repo->replace_for_room($room_id, $rows);

        if (!$ok) {
            throw new RuntimeException('RoomHoursFactory create failed: replace_for_room returned false.');
        }

        return $repo->get_by_room($room_id);
    }

    /**
     * Returns a Mon–Fri open, Sat–Sun closed schedule using snake_case keys
     * as expected by RoomHoursRepository::replace_for_room().
     */
    public static function default_week_rows(
        string $opening_time = '09:00:00',
        string $closing_time = '17:00:00'
    ): array {
        $rows = [];
        for ($day = 0; $day <= 6; $day++) {
            $is_weekend = ($day === 0 || $day === 6);
            $rows[] = [
                'day_of_week'     => $day,
                'use_venue_hours' => 0,
                'is_closed'       => $is_weekend ? 1 : 0,
                'opening_time'    => $is_weekend ? null : $opening_time,
                'closing_time'    => $is_weekend ? null : $closing_time,
            ];
        }
        return $rows;
    }
}
