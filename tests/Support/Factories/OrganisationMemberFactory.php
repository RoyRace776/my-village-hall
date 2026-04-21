<?php

namespace Tests\Support\Factories;

use MYVH\Organisations\OrganisationService;
use RuntimeException;
use WP_Error;

class OrganisationMemberFactory
{
    public static function make(array $overrides = []): array
    {
        return array_merge([
            'Id'                  => 0,
            'OrganisationId'     => 1,
            'CustomerId'         => 1,
            'IsOrganisationAdmin' => '0',
            'Created'             => '2026-01-01 00:00:00',
        ], $overrides);
    }

    public static function create(array $overrides = []): array
    {
        $organisation = isset($overrides['organisation_id'])
            ? null
            : OrganisationFactory::create();

        $customer = isset($overrides['customer_id'])
            ? null
            : CustomerFactory::create();

        $organisation_id = (int) ($overrides['organisation_id'] ?? $organisation['Id']);
        $customer_id = (int) ($overrides['customer_id'] ?? $customer['Id']);
        $is_admin = !empty($overrides['is_admin']);

        $service = app(OrganisationService::class);
        $member_id = $service->add_member($organisation_id, $customer_id, $is_admin);

        if ($member_id instanceof WP_Error) {
            throw new RuntimeException('OrganisationMemberFactory create failed: ' . $member_id->get_error_message());
        }

        if (!is_int($member_id) || $member_id <= 0) {
            throw new RuntimeException('OrganisationMemberFactory create failed: invalid member id returned.');
        }

        $members = $service->get_members($organisation_id);
        foreach ($members as $member) {
            if ((int) ($member['Id'] ?? 0) === $member_id) {
                return $member;
            }
        }

        throw new RuntimeException('OrganisationMemberFactory create failed: member could not be loaded.');
    }
}
