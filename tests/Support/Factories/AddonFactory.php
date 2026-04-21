<?php

namespace Tests\Support\Factories;

use Faker\Factory;
use MYVH\Addons\AddonService;
use RuntimeException;
use WP_Error;

class AddonFactory
{
    public static function make(array $overrides = []): array
    {
        $faker = Factory::create();

        return array_merge([
            'Id'           => 0,
            'Name'         => ucfirst($faker->unique()->word) . ' Add-on',
            'Description'  => $faker->sentence,
            'Price'        => $faker->randomFloat(2, 1, 100),
            'ChargeType'   => 'per_booking',
            'RoomId'       => null,
            'VenueId'      => null,
            'IsActive'     => '1',
            'DisplayOrder' => '0',
            'Created'      => '2026-01-01 00:00:00',
            'ArchivedAt'   => null,
        ], $overrides);
    }

    public static function create(array $overrides = []): array
    {
        $faker   = Factory::create();
        $service = app(AddonService::class);

        // AddonService::save() uses isset($data['is_active']) ? 1 : 0
        // so the key must be present to create an active addon.
        $addon_id = $service->save(array_merge([
            'name'          => ucfirst($faker->unique()->word) . ' Add-on',
            'description'   => $faker->sentence,
            'price'         => $faker->randomFloat(2, 1, 100),
            'charge_type'   => 'per_booking',
            'is_active'     => 1,
            'display_order' => 0,
        ], $overrides));

        if ($addon_id instanceof WP_Error) {
            throw new RuntimeException('AddonFactory create failed: ' . $addon_id->get_error_message());
        }

        if (!is_int($addon_id) || $addon_id <= 0) {
            throw new RuntimeException('AddonFactory create failed: invalid addon id returned.');
        }

        $addon = $service->get($addon_id);
        if (!$addon) {
            throw new RuntimeException('AddonFactory create failed: addon could not be loaded.');
        }

        return $addon;
    }
}
