<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Bookings\BookingStatus;
use MYVH\Tests\Unit\UnitTestCase;

class BookingAccessControlTest extends UnitTestCase {
    private $booking_repo;
    private $organisation_repo;
    private $service;

    protected function setUp(): void {
        parent::setUp();

        $this->booking_repo = $this->mock(\MYVH\Bookings\BookingRepository::class);
        $this->organisation_repo = $this->mock(\MYVH\Organisations\OrganisationRepository::class);

        $this->service = new \MYVH\Bookings\Services\BookingAccessControl(
            $this->booking_repo,
            $this->organisation_repo
        );
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
            'Status' => BookingStatus::PENDING,
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
            'Status' => BookingStatus::CONFIRMED,
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
    public function can_edit_returns_permissive_default(): void {
        $result = $this->service->can_edit(['Id' => 1]);

        $this->assertTrue($result['can_edit']);
        $this->assertSame('', $result['reason']);
    }
}
