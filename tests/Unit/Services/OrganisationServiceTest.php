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
            'do_action' => static fn() => null,
            'get_current_user_id' => static fn() => 1,
            'get_current_blog_id' => static fn() => 1,
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
            ->andReturnUsing(static fn(): array => ['Id' => 7, 'Name' => 'Community Group']);

        $this->repo->shouldReceive('count_all')->once()->andReturn(1);
        $this->repo->shouldReceive('has_default')->once()->andReturn(true);
        $this->repo->shouldReceive('create')
            ->once()
            ->withArgs(function (array $record): bool {
                return $record['OrganisationTypeId'] === 7
                    && $record['Name'] === 'My New Org'
                    && $record['IsActive'] === 1
                    && $record['SendBookingEmailsToOrganisation'] === 0;
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
            ->andReturnUsing(static fn(): array => [
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
            ->andReturnUsing(static fn(): array => [
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

    /** @test */
    public function admin_can_change_organisation_type_on_edit(): void {
        $this->repo->shouldReceive('get_by_id')
            ->once()
            ->with(10)
            ->andReturnUsing(static fn(): array => [
                'Id' => 10,
                'Name' => 'Example Org',
                'OrganisationTypeId' => 2,
                'IsSystem' => 0,
                'IsActive' => 1,
                'IsDefault' => 0,
                'DefaultPublic' => 0,
            ]);

        $this->repo->shouldReceive('update')
            ->once()
            ->withArgs(function (array $record, array $where): bool {
                return $record['OrganisationTypeId'] === 3
                    && $record['Name'] === 'Example Org'
                    && $where['Id'] === 10;
            })
            ->andReturn(true);

        $this->repo->shouldReceive('has_default')
            ->once()
            ->andReturn(true);

        $result = $this->service->save([
            'organisation_id' => 10,
            'name' => 'Example Org',
            'organisation_type_id' => 3,
            'contact_email' => 'hello@example.org',
            'contact_phone' => '01234567',
            'invoice_organisation_bookings' => 0,
            'is_active' => 1,
            'is_default' => 0,
            'default_public' => 0,
        ], true);

        $this->assertTrue($result);
    }

    /** @test */
    public function update_billing_details_by_admin_updates_contact_email_and_phone(): void {
        $this->member_repo->shouldReceive('is_customer_admin')
            ->once()
            ->with(12, 77)
            ->andReturn(true);

        $this->repo->shouldReceive('get_by_id')
            ->once()
            ->with(12)
            ->andReturnUsing(static fn(): array => [
                'Id' => 12,
                'ContactEmail' => 'old@example.org',
                'ContactPhone' => '01234 000000',
            ]);

        $this->repo->shouldReceive('update')
            ->once()
            ->withArgs(function (array $record, array $where): bool {
                return $record['ContactEmail'] === 'new@example.org'
                    && $record['ContactPhone'] === '07700 900111'
                    && $record['SendBookingEmailsToOrganisation'] === 1
                    && $record['InvoiceOrganisationBookings'] === 1
                    && $where['Id'] === 12;
            })
            ->andReturn(true);

        $result = $this->service->update_billing_details_by_admin(12, 77, [
            'contact_email' => 'new@example.org',
            'contact_phone' => '07700 900111',
            'send_booking_emails_to_organisation' => 1,
            'invoice_organisation_bookings' => 1,
            'billing_contact_name' => 'Accounts Team',
            'billing_email' => 'billing@example.org',
        ]);

        $this->assertTrue($result);
    }

    /** @test */
    public function update_billing_details_by_admin_uses_existing_contact_details_when_not_provided(): void {
        $this->member_repo->shouldReceive('is_customer_admin')
            ->once()
            ->with(13, 88)
            ->andReturn(true);

        $this->repo->shouldReceive('get_by_id')
            ->once()
            ->with(13)
            ->andReturnUsing(static fn(): array => [
                'Id' => 13,
                'ContactEmail' => 'retained@example.org',
                'ContactPhone' => '01234 111111',
                'SendBookingEmailsToOrganisation' => 1,
            ]);

        $this->repo->shouldReceive('update')
            ->once()
            ->withArgs(function (array $record, array $where): bool {
                return $record['ContactEmail'] === 'retained@example.org'
                    && $record['ContactPhone'] === '01234 111111'
                    && $record['SendBookingEmailsToOrganisation'] === 1
                    && $record['InvoiceOrganisationBookings'] === 0
                    && $where['Id'] === 13;
            })
            ->andReturn(true);

        $result = $this->service->update_billing_details_by_admin(13, 88, [
            'invoice_organisation_bookings' => 0,
        ]);

        $this->assertTrue($result);
    }

    /** @test */
    public function delete_returns_not_found_when_organisation_does_not_exist(): void {
        $this->repo->shouldReceive('get_by_id')
            ->once()
            ->with(404)
            ->andReturn(null);

        $result = $this->service->delete(404);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }

    /** @test */
    public function delete_blocks_organisations_with_bookings(): void {
        $this->repo->shouldReceive('get_by_id')
            ->once()
            ->with(14)
            ->andReturnUsing(static fn(): array => [
                'Id' => 14,
                'IsSystem' => 0,
            ]);

        $this->repo->shouldReceive('count_bookings_for_organisation')
            ->once()
            ->with(14)
            ->andReturn(3);

        $this->repo->shouldReceive('delete')->never();

        $result = $this->service->delete(14);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('forbidden', $result->get_error_code());
    }

    /** @test */
    public function delete_allows_organisation_without_bookings(): void {
        $this->repo->shouldReceive('get_by_id')
            ->once()
            ->with(15)
            ->andReturnUsing(static fn(): array => [
                'Id' => 15,
                'IsSystem' => 0,
            ]);

        $this->repo->shouldReceive('count_bookings_for_organisation')
            ->once()
            ->with(15)
            ->andReturn(0);

        $this->repo->shouldReceive('delete')
            ->once()
            ->with(15)
            ->andReturn(true);

        $result = $this->service->delete(15);

        $this->assertTrue($result);
    }
}
