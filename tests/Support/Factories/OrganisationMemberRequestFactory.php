<?php

namespace Tests\Support\Factories;

use Faker\Factory;
use MYVH\Organisations\OrganisationService;
use RuntimeException;
use WP_Error;

class OrganisationMemberRequestFactory
{
    public static function make(array $overrides = []): array
    {
        $faker = Factory::create();

        return array_merge([
            'Id'                   => 0,
            'OrganisationId'       => 1,
            'CustomerId'           => 1,
            'Status'               => 'pending',
            'RequestMessage'       => $faker->sentence,
            'ReviewedByCustomerId' => null,
            'ReviewedAt'           => null,
            'Created'              => '2026-01-01 00:00:00',
        ], $overrides);
    }

    public static function create(array $overrides = []): array
    {
        $faker        = Factory::create();
        $organisation = isset($overrides['organisation_id']) ? null : OrganisationFactory::create();
        $customer     = isset($overrides['customer_id'])     ? null : CustomerFactory::create();

        $organisation_id = (int) ($overrides['organisation_id'] ?? $organisation['Id']);
        $customer_id     = (int) ($overrides['customer_id']     ?? $customer['Id']);
        $message         = $overrides['request_message'] ?? $faker->sentence;

        $service    = app(OrganisationService::class);
        $request_id = $service->create_membership_request($organisation_id, $customer_id, $message);

        if ($request_id instanceof WP_Error) {
            throw new RuntimeException('OrganisationMemberRequestFactory create failed: ' . $request_id->get_error_message());
        }

        if (!is_int($request_id) || $request_id <= 0) {
            throw new RuntimeException('OrganisationMemberRequestFactory create failed: invalid request id returned.');
        }

        $requests = $service->get_pending_requests_for_customer($customer_id);
        foreach ($requests as $request) {
            if ((int) ($request['Id'] ?? 0) === $request_id) {
                return $request;
            }
        }

        throw new RuntimeException('OrganisationMemberRequestFactory create failed: request could not be loaded.');
    }
}
