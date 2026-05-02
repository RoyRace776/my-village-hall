<?php

namespace Tests\Support\Factories;

use Faker\Factory;
use MYVH\Pricing\RoomRateService;
use RuntimeException;
use WP_Error;

class RoomRateFactory
{
    public static function make(array $overrides = []): array
    {
        $faker = Factory::create();

        return array_merge([
            'Id'                 => 0,
            'RoomId'             => 1,
            'OrganisationTypeId' => null,
            'ChargeType'         => 'per_hour',
            'Rate'               => number_format($faker->randomFloat(2, 5, 200), 2, '.', ''),
            'Name'               => ucfirst($faker->unique()->word) . ' Rate',
            'Description'        => $faker->sentence,
            'MinimumHours'       => null,
            'IsActive'           => '1',
            'ValidFrom'          => null,
            'ValidTo'            => null,
            'Priority'           => '0',
            'Created'            => date('Y-m-d H:i:s'),
        ], $overrides);
    }

    public static function create(array $overrides = []): array
    {
        $faker = Factory::create();

        $room = isset($overrides['room_id'])
            ? null
            : RoomFactory::create();

        $service = app(RoomRateService::class);

        $rate_id = $service->save(array_merge([
            'room_id' => $overrides['room_id'] ?? $room['Id'],
            'organisation_type_id' => $overrides['organisation_type_id'] ?? null,
            'charge_type' => 'per_hour',
            'rate' => $faker->randomFloat(2, 5, 200),
            'name' => ucfirst($faker->unique()->word) . ' Rate',
            'description' => $faker->sentence,
            'minimum_hours' => null,
            'is_active' => 1,
            'valid_from' => null,
            'valid_to' => null,
            'priority' => 0,
        ], $overrides));

        if ($rate_id instanceof WP_Error) {
            throw new RuntimeException('RoomRateFactory create failed: ' . $rate_id->get_error_message());
        }

        if (!is_int($rate_id) || $rate_id <= 0) {
            throw new RuntimeException('RoomRateFactory create failed: invalid rate id returned.');
        }

        $rate = $service->get($rate_id);
        if (!$rate) {
            throw new RuntimeException('RoomRateFactory create failed: rate could not be loaded.');
        }

        return $rate;
    }
}
