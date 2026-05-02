<?php

namespace Tests\Support\Factories;

use Faker\Factory;
use MYVH\Rooms\RoomService;
use RuntimeException;
use WP_Error;

class RoomFactory
{
    public static function make(array $overrides = []): array
    {
        $faker = Factory::create();

        return array_merge([
            'Id'                    => 0,
            'VenueId'              => 1,
            'Name'                 => ucfirst($faker->unique()->word) . ' Room',
            'Colour'               => null,
            'Description'          => $faker->sentence,
            'BufferBefore'         => '0',
            'BufferAfter'          => '0',
            'Capacity'             => (string) $faker->numberBetween(10, 200),
            'OpeningTime'          => '09:00:00',
            'ClosingTime'          => '17:00:00',
            'AllowMultiDayBookings' => '0',
            'CalcClosedHours'      => '0',
            'IsPublic'             => '1',
        ], $overrides);
    }

    public static function create(array $overrides = []): array
    {
        $faker = Factory::create();

        $venue = isset($overrides['venue_id'])
            ? null
            : VenueFactory::create();

        $service = app(RoomService::class);
        $room_id = $service->save(array_merge([
            'name' => ucfirst($faker->word) . ' Room',
            'venue_id' => $overrides['venue_id'] ?? $venue['Id'],
            'capacity' => 100,
            'description' => '',
            'opening_time' => '09:00:00',
            'closing_time' => '17:00:00',
        ], $overrides));

        if ($room_id instanceof WP_Error) {
            throw new RuntimeException('RoomFactory create failed: ' . $room_id->get_error_message());
        }

        $room = $service->get($room_id);
        if (!$room) {
            throw new RuntimeException('RoomFactory create failed: room could not be loaded.');
        }

        return $room;
    }
}