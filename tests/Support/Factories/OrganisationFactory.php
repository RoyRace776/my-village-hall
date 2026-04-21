<?php

namespace Tests\Support\Factories;

use Faker\Factory;
use MYVH\Organisations\OrganisationService;
use RuntimeException;
use WP_Error;

class OrganisationFactory
{
    public static function make(array $overrides = []): array
    {
        return array_merge([
            'Id'                              => 0,
            'Name'                            => 'Test Organisation',
            'OrganisationTypeId'              => 1,
            'ContactEmail'                    => 'org@example.org',
            'ContactPhone'                    => '0123456789',
            'WebsiteUrl'                      => null,
            'InvoiceOrganisationBookings'     => '0',
            'SendBookingEmailsToOrganisation' => '0',
            'AllowAutoConfirm'                => '0',
            'BillingContactName'              => null,
            'BillingEmail'                    => null,
            'BillingAddressLine1'             => null,
            'BillingAddressLine2'             => null,
            'BillingTownCity'                 => null,
            'BillingPostcode'                 => null,
            'BillingReference'                => null,
            'IsActive'                        => '1',
            'IsDefault'                       => '0',
            'IsSystem'                        => '0',
            'DefaultPublic'                   => '0',
            'Created'                         => '2026-01-01 00:00:00',
        ], $overrides);
    }

    public static function create(array $overrides = []): array
    {
        $faker = Factory::create();

        $type = isset($overrides['organisation_type_id'])
            ? null
            : OrganisationTypeFactory::create();

        $service = app(OrganisationService::class);

        $organisation_id = $service->save(array_merge([
            'name' => $faker->unique()->company,
            'organisation_type_id' => $overrides['organisation_type_id'] ?? $type['Id'],
            'contact_email' => $faker->unique()->companyEmail,
            'contact_phone' => $faker->phoneNumber,
            'website_url' => $faker->url,
            'is_active' => 1,
            'is_default' => 0,
            'default_public' => 0,
            'invoice_organisation_bookings' => 0,
            'send_booking_emails_to_organisation' => 0,
        ], $overrides));

        if ($organisation_id instanceof WP_Error) {
            throw new RuntimeException('OrganisationFactory create failed: ' . $organisation_id->get_error_message());
        }

        if (!is_int($organisation_id) || $organisation_id <= 0) {
            throw new RuntimeException('OrganisationFactory create failed: invalid organisation id returned.');
        }

        $organisation = $service->get($organisation_id);
        if (!$organisation) {
            throw new RuntimeException('OrganisationFactory create failed: organisation could not be loaded.');
        }

        return $organisation;
    }
}
