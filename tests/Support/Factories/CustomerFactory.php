<?php

namespace Tests\Support\Factories;

use MYVH\Customers\CustomerService;
use Faker\Factory;
use RuntimeException;
use WP_Error;

class CustomerFactory
{
    public static function make(array $overrides = []): array
    {
        $faker = Factory::create();

        return array_merge([
            'Id'               => 0,
            'Name'             => $faker->name,
            'WPUserId'         => null,
            'Email'            => $faker->unique()->safeEmail,
            'EmailVerified'    => '0',
            'PhoneNumber'      => $faker->phoneNumber,
            'PostCode'         => $faker->postcode,
            'AddressLine1'     => $faker->streetAddress,
            'AddressLine2'     => '',
            'AllowAutoConfirm' => '0',
            'Created'          => '2026-01-01 00:00:00',
            'Updated'          => '2026-01-01 00:00:00',
        ], $overrides);
    }

    public static function create(array $overrides = []): array
    {
        $faker = Factory::create();
        $service = app(CustomerService::class);

        $customer_id = $service->save(array_merge([
            'name'         => $faker->name,
            'email'        => $faker->unique()->safeEmail,
            'phone_number' => $faker->phoneNumber,
            'post_code'    => $faker->postcode,
            'address_line1' => $faker->streetAddress,
        ], $overrides));

        if ($customer_id instanceof WP_Error) {
            throw new RuntimeException('CustomerFactory create failed: ' . $customer_id->get_error_message());
        }

        $customer = $service->get($customer_id);
        if (!$customer) {
            throw new RuntimeException('CustomerFactory create failed: customer could not be loaded.');
        }

        return $customer;
    }
}