<?php

namespace Tests\Support\Factories;

use MYVH\Venues\VenueHoursRepository;
use RuntimeException;

class VenueHoursFactory
{
    /**
     * Returns a single venue-hours row (not persisted).
     * day_of_week: 0=Sunday, 1=Monday ... 6=Saturday
     */
    public static function make(array $overrides = []): array
    {
        return array_merge([
            'Id'          => 0,
            'VenueId'     => 1,
            'DayOfWeek'   => 1,
            'IsClosed'    => '0',
            'OpeningTime' => '08:00:00',
            'ClosingTime' => '22:00:00',
        ], $overrides);
    }

    /**
     * Persists a full week of venue hours and returns the saved rows.
     *
     * Pass `venue_id` to target an existing venue, or let the factory create one.
     * Pass `rows` (snake_case keys matching replace_for_venue()) to override the
     * default Mon–Fri 08:00–22:00 schedule.
     *
     * @return array  Array of saved row arrays, one per persisted day.
     */
    public static function create(array $overrides = []): array
    {
        $venue    = isset($overrides['venue_id']) ? null : VenueFactory::create();
        $venue_id = (int) ($overrides['venue_id'] ?? $venue['Id']);
        $rows     = $overrides['rows'] ?? self::default_week_rows();

        $repo = app(VenueHoursRepository::class);
        $ok   = $repo->replace_for_venue($venue_id, $rows);

        if (!$ok) {
            throw new RuntimeException('VenueHoursFactory create failed: replace_for_venue returned false.');
        }

        return $repo->get_by_venue($venue_id);
    }

    /**
     * Returns a Mon–Fri open, Sat–Sun closed schedule using snake_case keys
     * as expected by VenueHoursRepository::replace_for_venue().
     */
    public static function default_week_rows(
        string $opening_time = '08:00:00',
        string $closing_time = '22:00:00'
    ): array {
        $rows = [];
        for ($day = 0; $day <= 6; $day++) {
            $is_weekend = ($day === 0 || $day === 6);
            $rows[] = [
                'day_of_week'  => $day,
                'is_closed'    => $is_weekend ? 1 : 0,
                'opening_time' => $is_weekend ? null : $opening_time,
                'closing_time' => $is_weekend ? null : $closing_time,
            ];
        }
        return $rows;
    }
}
