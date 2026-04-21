<?php

namespace Tests\Support\Factories;

use Faker\Factory;
use MYVH\Pricing\DiscountRepository;
use RuntimeException;

class DiscountFactory
{
    public static function make(array $overrides = []): array
    {
        $faker = Factory::create();

        return array_merge([
            'Id'              => 0,
            'Code'            => strtoupper($faker->unique()->lexify('DISC-????')),
            'Description'     => $faker->sentence,
            'DiscountType'    => 'percentage',
            'DiscountValue'   => $faker->randomFloat(2, 5, 50),
            'MinimumAmount'   => '0.00',
            'MaximumDiscount' => null,
            'ValidFrom'       => null,
            'ValidTo'         => null,
            'UsageLimit'      => null,
            'UsageCount'      => '0',
            'IsActive'        => '1',
            'RoomId'          => null,
            'VenueId'         => null,
            'Created'         => '2026-01-01 00:00:00',
        ], $overrides);
    }

    /**
     * Persists a discount record directly via the repository.
     * There is no DiscountService, so PascalCase column keys are used
     * when passing custom $overrides to create().
     */
    public static function create(array $overrides = []): array
    {
        $faker = Factory::create();
        $repo  = app(DiscountRepository::class);

        $discount_id = $repo->create(array_merge([
            'Code'          => strtoupper($faker->unique()->lexify('DISC-????')),
            'Description'   => $faker->sentence,
            'DiscountType'  => 'percentage',
            'DiscountValue' => $faker->randomFloat(2, 5, 50),
            'MinimumAmount' => 0.00,
            'IsActive'      => 1,
            'UsageCount'    => 0,
        ], $overrides));

        if ($discount_id === false) {
            throw new RuntimeException('DiscountFactory create failed: database insert returned false.');
        }

        $discount = $repo->get_by_id($discount_id);
        if (!$discount) {
            throw new RuntimeException('DiscountFactory create failed: discount could not be loaded.');
        }

        return $discount;
    }
}
