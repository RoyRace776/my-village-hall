<?php

namespace Tests\Support\Factories;

use Faker\Factory;
use MYVH\Organisations\OrganisationTypeService;
use RuntimeException;
use WP_Error;

class OrganisationTypeFactory
{
    public static function make(array $overrides = []): array
    {
        return array_merge([
            'Id'          => 0,
            'Name'        => 'Test Organisation Type',
            'Description' => '',
            'IsSystem'    => '0',
            'IsDefault'   => '0',
            'Created'     => '2026-01-01 00:00:00',
        ], $overrides);
    }

    public static function create(array $overrides = []): array
    {
        $faker = Factory::create();
        $service = app(OrganisationTypeService::class);

        $type_id = $service->save(array_merge([
            'name' => $faker->unique()->word . ' Group',
            'description' => $faker->sentence,
            'is_default' => 0,
        ], $overrides));

        if ($type_id instanceof WP_Error) {
            throw new RuntimeException('OrganisationTypeFactory create failed: ' . $type_id->get_error_message());
        }

        if (!is_int($type_id) || $type_id <= 0) {
            throw new RuntimeException('OrganisationTypeFactory create failed: invalid type id returned.');
        }

        $type = $service->get($type_id);
        if (!$type) {
            throw new RuntimeException('OrganisationTypeFactory create failed: type could not be loaded.');
        }

        return $type;
    }
}
