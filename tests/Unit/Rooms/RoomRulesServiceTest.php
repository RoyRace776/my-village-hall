<?php

namespace MYVH\Tests\Unit\Rooms;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Bookings\BookingRepository;
use MYVH\Rooms\RoomRulesService;
use MYVH\Tests\Unit\UnitTestCase;

class RoomRulesServiceTest extends UnitTestCase {

    private BookingRepository&MockInterface $repo;
    private RoomRulesService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->repo    = $this->mock( BookingRepository::class );
        $this->service = new RoomRulesService( $this->repo );
    }

    private function room( string $open = '08:00:00', string $close = '22:00:00', int $id = 1 ): array {
        return [
            'Id'          => $id,
            'OpeningTime' => $open,
            'ClosingTime' => $close,
        ];
    }

    // ── Buffer disabled ───────────────────────────────────────────────────────

    /** @test */
    public function returns_true_when_both_buffers_are_zero(): void {
        Functions\when( 'myvh_setting' )->alias( static fn( $key, $default = null ) => match ( $key ) {
            'booking.set_up_minutes'  => 0,
            'booking.tidy_up_minutes' => 0,
            default => $default,
        } );

        // No repo call expected.
        $this->repo->shouldNotReceive( 'has_conflict_in_buffer_window' );

        $this->assertTrue(
            $this->service->has_buffer_time( $this->room(), '2026-06-01 09:00', '2026-06-01 11:00' )
        );
    }

    // ── No conflict within buffer window ─────────────────────────────────────

    /** @test */
    public function returns_true_when_no_conflict_exists_in_buffer_window(): void {
        Functions\when( 'myvh_setting' )->alias( static fn( $key, $default = null ) => match ( $key ) {
            'booking.set_up_minutes'  => 30,
            'booking.tidy_up_minutes' => 30,
            default => $default,
        } );

        $this->repo->shouldReceive( 'has_conflict_in_buffer_window' )
            ->once()
            ->with( 1, '2026-06-01 08:30:00', '2026-06-01 11:30:00', null )
            ->andReturn( false );

        $this->assertTrue(
            $this->service->has_buffer_time( $this->room(), '2026-06-01 09:00', '2026-06-01 11:00' )
        );
    }

    // ── Conflict within buffer window ─────────────────────────────────────────

    /** @test */
    public function returns_false_when_conflict_exists_in_buffer_window(): void {
        Functions\when( 'myvh_setting' )->alias( static fn( $key, $default = null ) => match ( $key ) {
            'booking.set_up_minutes'  => 30,
            'booking.tidy_up_minutes' => 30,
            default => $default,
        } );

        $this->repo->shouldReceive( 'has_conflict_in_buffer_window' )
            ->once()
            ->andReturn( true );

        $this->assertFalse(
            $this->service->has_buffer_time( $this->room(), '2026-06-01 09:00', '2026-06-01 11:00' )
        );
    }

    // ── Opening time boundary exemption ──────────────────────────────────────

    /** @test */
    public function setup_buffer_is_waived_when_booking_starts_at_room_opening(): void {
        Functions\when( 'myvh_setting' )->alias( static fn( $key, $default = null ) => match ( $key ) {
            'booking.set_up_minutes'  => 30,
            'booking.tidy_up_minutes' => 30,
            default => $default,
        } );

        // Window should start at booking start (no setup) but extend 30 min after end.
        $this->repo->shouldReceive( 'has_conflict_in_buffer_window' )
            ->once()
            ->with( 1, '2026-06-01 08:00:00', '2026-06-01 11:30:00', null )
            ->andReturn( false );

        // Booking starts exactly at 08:00:00 room opening.
        $this->assertTrue(
            $this->service->has_buffer_time( $this->room( '08:00:00' ), '2026-06-01 08:00', '2026-06-01 11:00' )
        );
    }

    // ── Closing time boundary exemption ──────────────────────────────────────

    /** @test */
    public function tidy_buffer_is_waived_when_booking_ends_at_room_closing(): void {
        Functions\when( 'myvh_setting' )->alias( static fn( $key, $default = null ) => match ( $key ) {
            'booking.set_up_minutes'  => 30,
            'booking.tidy_up_minutes' => 30,
            default => $default,
        } );

        // Window should extend 30 min before start but end at booking end (no tidy).
        $this->repo->shouldReceive( 'has_conflict_in_buffer_window' )
            ->once()
            ->with( 1, '2026-06-01 09:30:00', '2026-06-01 22:00:00', null )
            ->andReturn( false );

        // Booking ends exactly at 22:00:00 room closing.
        $this->assertTrue(
            $this->service->has_buffer_time( $this->room( '08:00:00', '22:00:00' ), '2026-06-01 10:00', '2026-06-01 22:00' )
        );
    }

    // ── Both boundaries exempt ────────────────────────────────────────────────

    /** @test */
    public function returns_true_without_repo_call_when_both_boundaries_exempt_buffers(): void {
        Functions\when( 'myvh_setting' )->alias( static fn( $key, $default = null ) => match ( $key ) {
            'booking.set_up_minutes'  => 30,
            'booking.tidy_up_minutes' => 30,
            default => $default,
        } );

        // Full-day booking from opening to closing — no buffer needed.
        $this->repo->shouldNotReceive( 'has_conflict_in_buffer_window' );

        $this->assertTrue(
            $this->service->has_buffer_time( $this->room( '08:00:00', '22:00:00' ), '2026-06-01 08:00', '2026-06-01 22:00' )
        );
    }

    // ── Exclude booking ID forwarded ──────────────────────────────────────────

    /** @test */
    public function exclude_booking_id_is_forwarded_to_repository(): void {
        Functions\when( 'myvh_setting' )->alias( static fn( $key, $default = null ) => match ( $key ) {
            'booking.set_up_minutes'  => 15,
            'booking.tidy_up_minutes' => 0,
            default => $default,
        } );

        $this->repo->shouldReceive( 'has_conflict_in_buffer_window' )
            ->once()
            ->with( 1, '2026-06-01 08:45:00', '2026-06-01 11:00:00', 99 )
            ->andReturn( false );

        $this->assertTrue(
            $this->service->has_buffer_time( $this->room(), '2026-06-01 09:00', '2026-06-01 11:00', 99 )
        );
    }
}
