<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Organisations\OrganisationMemberRepository;
use MYVH\Organisations\OrganisationMemberRequestRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Organisations\OrganisationService;
use MYVH\Organisations\OrganisationTypeRepository;
use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class OrganisationServiceTest extends UnitTestCase {
    private $repo;
    private $member_repo;
    private $request_repo;
    private $type_repo;
    private $service;

    protected function setUp(): void {
        parent::setUp();

        \Brain\Monkey\Functions\stubs([
            'sanitize_email' => fn($v) => (string) $v,
            'is_email' => fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false,
            'esc_url_raw' => fn($v) => (string) $v,
            'current_time' => fn() => '2026-01-01 00:00:00',
        ]);

        $this->repo = $this->mock(OrganisationRepository::class);
        $this->member_repo = $this->mock(OrganisationMemberRepository::class);
        $this->request_repo = $this->mock(OrganisationMemberRequestRepository::class);
        $this->type_repo = $this->mock(OrganisationTypeRepository::class);

        $this->service = new OrganisationService(
            $this->repo,
            $this->member_repo,
            $this->request_repo,
            $this->type_repo
        );
    }

    /** @test */
    public function non_admin_create_uses_default_type(): void {
        $this->type_repo->shouldReceive('get_default')
            ->once()
            ->andReturn(['Id' => 7, 'Name' => 'Community Group']);

        $this->repo->shouldReceive('count_all')->once()->andReturn(1);
        $this->repo->shouldReceive('has_default')->once()->andReturn(true);
        $this->repo->shouldReceive('create')
            ->once()
            ->withArgs(function (array $record): bool {
                return $record['OrganisationTypeId'] === 7
                    && $record['Name'] === 'My New Org'
                    && $record['IsActive'] === 1;
            })
            ->andReturn(44);

        $result = $this->service->save([
            'name' => 'My New Org',
            'organisation_type_id' => 123,
            'contact_email' => 'hello@example.org',
            'contact_phone' => '01234567',
            'invoice_organisation_bookings' => 0,
        ], false);

        $this->assertSame(44, $result);
    }

    /** @test */
    public function non_admin_cannot_change_organisation_type_on_edit(): void {
        $this->repo->shouldReceive('get_by_id')
            ->once()
            ->with(10)
            ->andReturn([
                'Id' => 10,
                'Name' => 'Example Org',
                'OrganisationTypeId' => 2,
                'IsSystem' => 0,
                'IsActive' => 1,
            ]);

        $this->repo->shouldReceive('update')->never();

        $result = $this->service->save([
            'organisation_id' => 10,
            'name' => 'Example Org',
            'organisation_type_id' => 3,
            'contact_email' => 'hello@example.org',
            'contact_phone' => '01234567',
            'invoice_organisation_bookings' => 0,
        ], false);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('forbidden', $result->get_error_code());
    }

    /** @test */
    public function system_organisation_cannot_be_renamed(): void {
        $this->repo->shouldReceive('get_by_id')
            ->once()
            ->with(2)
            ->andReturn([
                'Id' => 2,
                'Name' => 'Personal booking',
                'OrganisationTypeId' => 1,
                'IsSystem' => 1,
                'IsActive' => 1,
            ]);

        $this->repo->shouldReceive('update')->never();

        $result = $this->service->save([
            'organisation_id' => 2,
            'name' => 'Renamed personal booking',
            'organisation_type_id' => 1,
            'contact_email' => 'hello@example.org',
            'contact_phone' => '01234567',
            'invoice_organisation_bookings' => 0,
            'is_active' => 1,
        ], true);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('forbidden', $result->get_error_code());
    }
}
