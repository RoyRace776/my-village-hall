<?php

namespace MYVH\Tests\Unit\Services;

use Brain\Monkey\Functions;
use MYVH\Bookings\BookingStatus;
use MYVH\Customers\CustomerRepository;
use MYVH\Organisations\OrganisationMemberRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Portal\ClientAdminService;
use MYVH\Tests\Unit\UnitTestCase;

class BookingAccessControlTest extends UnitTestCase {
    private $booking_repo;
    private $customer_repo;
    private $organisation_member_repo;
    private $organisation_repo;
    private $client_admin_service;
    private $service;

    protected function setUp(): void {
        parent::setUp();

        $this->booking_repo = $this->mock(\MYVH\Bookings\BookingRepository::class);
        $this->customer_repo = $this->mock(CustomerRepository::class);
        $this->organisation_member_repo = $this->mock(OrganisationMemberRepository::class);
        $this->organisation_repo = $this->mock(OrganisationRepository::class);
        $this->client_admin_service = $this->mock(ClientAdminService::class);

        $this->service = new \MYVH\Bookings\Services\BookingAccessControl(
            $this->booking_repo,
            $this->organisation_repo,
            $this->customer_repo,
            $this->organisation_member_repo,
            $this->client_admin_service
        );

        Functions\stubs([
            'get_current_user_id' => 21,
            'get_current_blog_id' => 7,
        ]);
    }

    /** @test */
    public function resolve_public_visibility_uses_explicit_payload_value_first(): void {
        $result = $this->service->resolve_public_visibility(['public' => 1], 10, 88);

        $this->assertSame(1, $result);
    }

    /** @test */
    public function resolve_public_visibility_falls_back_to_existing_booking_value(): void {
        $this->booking_repo->shouldReceive('get_by_id')
            ->with(88)
            ->once()
            ->andReturn(['Public' => 1]);

        $result = $this->service->resolve_public_visibility([], 10, 88);

        $this->assertSame(1, $result);
    }

    /** @test */
    public function resolve_public_visibility_falls_back_to_organisation_default(): void {
        $this->booking_repo->shouldReceive('get_by_id')
            ->with(88)
            ->once()
            ->andReturn(null);

        $this->organisation_repo->shouldReceive('get_by_id')
            ->with(10)
            ->once()
            ->andReturn(['DefaultPublic' => 1]);

        $result = $this->service->resolve_public_visibility([], 10, 88);

        $this->assertSame(1, $result);
    }

    /** @test */
    public function resolve_public_visibility_defaults_to_private_when_no_data_found(): void {
        $this->booking_repo->shouldReceive('get_by_id')
            ->with(88)
            ->once()
            ->andReturn(null);

        $this->organisation_repo->shouldReceive('get_by_id')
            ->with(10)
            ->once()
            ->andReturn(null);

        $result = $this->service->resolve_public_visibility([], 10, 88);

        $this->assertSame(0, $result);
    }

    /** @test */
    public function can_delete_allows_pending_future_booking(): void {
        \Brain\Monkey\Functions\when('myvh_setting')->justReturn(24);
        \Brain\Monkey\Functions\when('current_time')->justReturn(strtotime('2026-04-10 10:00:00'));

        $result = $this->service->can_delete([
            'StartDate' => '2026-04-15',
            'StartTime' => '10:00:00',
            'Status' => BookingStatus::PENDING->value,
        ]);

        $this->assertTrue($result['can_delete']);
        $this->assertSame('', $result['reason']);
    }

    /** @test */
    public function can_delete_allows_pending_past_booking(): void {
        \Brain\Monkey\Functions\when('myvh_setting')->justReturn(24);
        \Brain\Monkey\Functions\when('current_time')->justReturn(strtotime('2026-04-10 10:00:00'));

        $result = $this->service->can_delete([
            'StartDate' => '2026-04-01',
            'StartTime' => '10:00:00',
            'Status' => BookingStatus::PENDING->value,
        ]);

        $this->assertTrue($result['can_delete']);
        $this->assertSame('', $result['reason']);
    }

    /** @test */
    public function can_delete_blocks_confirmed_booking_inside_notice_window(): void {
        \Brain\Monkey\Functions\when('myvh_setting')->justReturn(48);
        \Brain\Monkey\Functions\when('current_time')->justReturn(strtotime('2026-04-10 10:00:00'));

        $result = $this->service->can_delete([
            'StartDate' => '2026-04-11',
            'StartTime' => '09:00:00',
            'Status' => BookingStatus::CONFIRMED->value,
        ]);

        $this->assertFalse($result['can_delete']);
        $this->assertSame(48, $result['min_notice_hours']);
    }

    /** @test */
    public function can_delete_blocks_unknown_status(): void {
        \Brain\Monkey\Functions\when('myvh_setting')->justReturn(24);
        \Brain\Monkey\Functions\when('current_time')->justReturn(strtotime('2026-04-10 10:00:00'));

        $result = $this->service->can_delete([
            'StartDate' => '2026-04-15',
            'StartTime' => '10:00:00',
            'Status' => 'draft',
        ]);

        $this->assertFalse($result['can_delete']);
    }

    /** @test */
    public function can_edit_blocks_invoiced_bookings_for_client_admin(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(true);
        $this->booking_repo->shouldReceive('has_invoiced_items')
            ->once()
            ->with(55)
            ->andReturn(true);

        $result = $this->service->can_edit([
            'Id' => 55,
            'Status' => BookingStatus::PENDING->value,
        ]);

        $this->assertFalse($result['can_edit']);
        $this->assertSame('Invoiced bookings cannot be edited.', $result['reason']);
    }

    /** @test */
    public function can_edit_allows_pending_booking_for_client_admin(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(true);
        $this->booking_repo->shouldReceive('has_invoiced_items')
            ->once()
            ->with(56)
            ->andReturn(false);

        $result = $this->service->can_edit([
            'Id' => 56,
            'Status' => BookingStatus::PENDING->value,
            'CustomerId' => 9,
            'OrganisationId' => 0,
        ]);

        $this->assertTrue($result['can_edit']);
        $this->assertSame('', $result['reason']);
    }

    /** @test */
    public function can_edit_allows_pending_booking_for_booker(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(false);
        $this->booking_repo->shouldReceive('has_invoiced_items')
            ->once()
            ->with(57)
            ->andReturn(false);
        $this->customer_repo->shouldReceive('get_by_user_id')
            ->once()
            ->with(21)
            ->andReturn(['Id' => 99]);

        $result = $this->service->can_edit([
            'Id' => 57,
            'Status' => BookingStatus::PENDING->value,
            'CustomerId' => 99,
            'OrganisationId' => 13,
        ]);

        $this->assertTrue($result['can_edit']);
        $this->assertSame('', $result['reason']);
    }

    /** @test */
    public function can_edit_allows_pending_booking_for_organisation_admin(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(false);
        $this->booking_repo->shouldReceive('has_invoiced_items')
            ->once()
            ->with(58)
            ->andReturn(false);
        $this->customer_repo->shouldReceive('get_by_user_id')
            ->once()
            ->with(21)
            ->andReturn(['Id' => 77]);
        $this->organisation_member_repo->shouldReceive('is_customer_admin')
            ->once()
            ->with(13, 77)
            ->andReturn(true);

        $result = $this->service->can_edit([
            'Id' => 58,
            'Status' => BookingStatus::PENDING->value,
            'CustomerId' => 99,
            'OrganisationId' => 13,
        ]);

        $this->assertTrue($result['can_edit']);
        $this->assertSame('', $result['reason']);
    }

    /** @test */
    public function can_edit_blocks_pending_booking_for_unrelated_user(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(false);
        $this->booking_repo->shouldReceive('has_invoiced_items')
            ->once()
            ->with(59)
            ->andReturn(false);
        $this->customer_repo->shouldReceive('get_by_user_id')
            ->once()
            ->with(21)
            ->andReturn(['Id' => 77]);
        $this->organisation_member_repo->shouldReceive('is_customer_admin')
            ->once()
            ->with(13, 77)
            ->andReturn(false);

        $result = $this->service->can_edit([
            'Id' => 59,
            'Status' => BookingStatus::PENDING->value,
            'CustomerId' => 99,
            'OrganisationId' => 13,
        ]);

        $this->assertFalse($result['can_edit']);
        $this->assertSame('Only the booker, an organisation admin, or a client administrator can edit pending bookings.', $result['reason']);
    }

    /** @test */
    public function can_edit_allows_confirmed_booking_for_client_admin(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(true);
        $this->booking_repo->shouldReceive('has_invoiced_items')
            ->once()
            ->with(60)
            ->andReturn(false);

        $result = $this->service->can_edit([
            'Id' => 60,
            'Status' => BookingStatus::CONFIRMED->value,
        ]);

        $this->assertTrue($result['can_edit']);
        $this->assertSame('', $result['reason']);
    }

    /** @test */
    public function can_edit_blocks_confirmed_booking_for_non_admin(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(false);
        $this->booking_repo->shouldReceive('has_invoiced_items')
            ->once()
            ->with(61)
            ->andReturn(false);

        $result = $this->service->can_edit([
            'Id' => 61,
            'Status' => BookingStatus::CONFIRMED->value,
        ]);

        $this->assertFalse($result['can_edit']);
        $this->assertSame('Only client administrators can edit confirmed or cancelled bookings.', $result['reason']);
    }

    /** @test */
    public function can_edit_blocks_cancelled_booking_for_non_admin(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(false);
        $this->booking_repo->shouldReceive('has_invoiced_items')
            ->once()
            ->with(62)
            ->andReturn(false);

        $result = $this->service->can_edit([
            'Id' => 62,
            'Status' => BookingStatus::CANCELLED->value,
        ]);

        $this->assertFalse($result['can_edit']);
        $this->assertSame('Only client administrators can edit confirmed or cancelled bookings.', $result['reason']);
    }

    /** @test */
    public function can_edit_blocks_completed_booking(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(true);
        $this->booking_repo->shouldReceive('has_invoiced_items')
            ->once()
            ->with(63)
            ->andReturn(false);

        $result = $this->service->can_edit([
            'Id' => 63,
            'Status' => BookingStatus::COMPLETED->value,
        ]);

        $this->assertFalse($result['can_edit']);
        $this->assertSame('This booking cannot be edited.', $result['reason']);
    }
}
