<?php

namespace Tests\Support\Factories;

use Faker\Factory;
use MYVH\Venues\VenueService;
use RuntimeException;
use WP_Error;

class VenueFactory
{
    public static function make(array $overrides = []): array
    {
        $faker = Factory::create('en_GB');

        return array_merge([
            'Id'           => 0,
            'Name'         => $faker->unique()->company . ' Hall',
            'ShortName'    => strtoupper($faker->lexify('??')),
            'PostCode'     => $faker->postcode,
            'AddressLine1' => $faker->streetAddress,
            'OpeningTime'  => '08:00:00',
            'ClosingTime'  => '22:00:00',
        ], $overrides);
    }

    public static function create(array $overrides = []): array
    {
        $faker = Factory::create();
        $service = app(VenueService::class);

        $venue_id = $service->save(array_merge([
            'name' => $faker->unique()->company . ' Hall',
            'short_name' => strtoupper($faker->lexify('??')),
            'post_code' => $faker->postcode,
            'address_line1' => $faker->streetAddress,
            'opening_time' => '08:00:00',
            'closing_time' => '22:00:00',
        ], $overrides));

        if ($venue_id instanceof WP_Error) {
            throw new RuntimeException('VenueFactory create failed: ' . $venue_id->get_error_message());
        }

        $venue = $service->get($venue_id);
        if (!$venue) {
            throw new RuntimeException('VenueFactory create failed: venue could not be loaded.');
        }

        return $venue;
    }
}
