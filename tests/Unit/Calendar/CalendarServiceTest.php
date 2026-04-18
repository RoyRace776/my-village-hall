<?php

namespace MYVH\Tests\Unit\Calendar;

use Brain\Monkey\Functions;
use MYVH\Availability\AvailabilityService;
use MYVH\Bookings\Booking;
use MYVH\Bookings\BookingStatus;
use MYVH\Bookings\BookingService;
use MYVH\Calendar\CalendarService;
use MYVH\Customers\CustomerService;
use MYVH\Portal\ClientAdminService;
use MYVH\Rooms\RoomRepository;
use MYVH\Tests\Unit\UnitTestCase;

/**
 * Unit tests for CalendarService buffer event generation.
 *
 * Only the buffer-rendering paths are tested here. Permissions, recurring
 * patterns, and public-feed visibility are covered elsewhere.
 */
class CalendarServiceTest extends UnitTestCase {

    private $booking_service;
    private $room_repository;
    private $availability_service;
    private $customer_service;
    private $client_admin_service;
    private string $room_open = '08:00:00';
    private string $room_close = '22:00:00';
    private CalendarService $service;

    protected function setUp(): void {
        parent::setUp();

        $this->booking_service      = $this->mock( BookingService::class );
        $this->room_repository      = $this->mock( RoomRepository::class );
        $this->availability_service = $this->mock( AvailabilityService::class );
        $this->customer_service     = $this->mock( CustomerService::class );
        $this->client_admin_service = $this->mock( ClientAdminService::class );

        $this->availability_service->shouldReceive('get_effective_room_hours_for_date')
            ->andReturnUsing(function () {
                return [
                    'is_closed' => 0,
                    'opening_time' => $this->room_open,
                    'closing_time' => $this->room_close,
                ];
            });

        $this->service = new CalendarService(
            $this->booking_service,
            $this->room_repository,
            $this->availability_service,
            $this->customer_service,
            $this->client_admin_service
        );

        Functions\stubs( [
            'current_user_can'     => true,
            'get_current_user_id'  => 1,
            'get_current_blog_id'  => 1,
            'apply_filters'        => static fn( $hook, $value ) => $value,
            'is_user_logged_in'    => true,
        ] );
    }

    // ── Shared test helpers ───────────────────────────────────────────────────

    private function stub_settings( int $setup, int $tidy, bool $separately = false, string $text = 'Buffer' ): void {
        Functions\when( 'myvh_setting' )->alias(
            static fn( $key, $default = null ) => match ( $key ) {
                'booking.buffers.set_up_minutes'           => $setup,
                'booking.buffers.tidy_up_minutes'          => $tidy,
                'booking.buffers.show_buffer_times_separately' => $separately,
                'booking.buffers.buffer_text'              => $text,
                default                                    => $default,
            }
        );
    }

    private function single_booking( array $overrides = [] ): array {
        return array_merge( [
            'Id'             => 10,
            'RoomId'         => 5,
            'CustomerId'     => 3,
            'OrganisationId' => 0,
            'Status'         => 'confirmed',
            'StartDate'      => '2026-06-01',
            'StartTime'      => '10:00:00',
            'EndDate'        => '2026-06-01',
            'EndTime'        => '12:00:00',
            'Description'    => 'Community lunch',
            'Public'         => 1,
        ], $overrides );
    }

    private function wire_room_meta( string $open = '08:00:00', string $close = '22:00:00' ): void {
        $this->room_open = $open;
        $this->room_close = $close;

        $this->room_repository->shouldReceive( 'get_all_with_venues' )
            ->andReturn( [ [
                'Id'          => 5,
                'Name'        => 'Main Hall',
                'VenueName'   => 'Village Hall',
                'Colour'      => '#123456',
                'IsPublic'    => 1,
                'OpeningTime' => $open,
                'ClosingTime' => $close,
            ] ] );
    }

    private function wire_booking( array $booking ): void {
        $booking_entity = Booking::fromDatabaseRow([
            'Id' => (int) ($booking['Id'] ?? 10),
            'CustomerId' => (int) ($booking['CustomerId'] ?? 3),
            'RoomId' => (int) ($booking['RoomId'] ?? 5),
            'OrganisationId' => (int) ($booking['OrganisationId'] ?? 0),
            'Status' => BookingStatus::from((string) ($booking['Status'] ?? BookingStatus::CONFIRMED->value)),
            'Start' => new \DateTimeImmutable(($booking['StartDate'] ?? '2026-06-01') . ' ' . ($booking['StartTime'] ?? '10:00:00')),
            'End' => new \DateTimeImmutable(($booking['EndDate'] ?? '2026-06-01') . ' ' . ($booking['EndTime'] ?? '12:00:00')),
            'AdminEmail' => null,
            'Description' => (string) ($booking['Description'] ?? ''),
            'Public' => !empty($booking['Public']),
            'RoomName' => (string) ($booking['RoomName'] ?? ''),
            'VenueName' => (string) ($booking['VenueName'] ?? ''),
            'IsPublic' => !empty($booking['IsPublic']),
        ]);

        $this->booking_service->shouldReceive( 'get_between' )
            ->once()
            ->andReturn( [ $booking_entity ] );
    }

    private function wire_booking_entity( array $overrides = [] ): void {
        $booking = Booking::fromDatabaseRow([
            'Id' => $overrides['Id'] ?? 10,
            'CustomerId' => $overrides['CustomerId'] ?? 3,
            'RoomId' => $overrides['RoomId'] ?? 5,
            'OrganisationId' => $overrides['OrganisationId'] ?? 0,
            'Status' => $overrides['Status'] ?? BookingStatus::CONFIRMED,
            'Start' => $overrides['Start'] ?? new \DateTimeImmutable('2026-06-01 10:00:00'),
            'End' => $overrides['End'] ?? new \DateTimeImmutable('2026-06-01 12:00:00'),
            'AdminEmail' => null,
            'Description' => (string) ($overrides['Description'] ?? 'Community lunch'),
            'Public' => !empty($overrides['Public'] ?? 1),
            'RoomName' => (string) ($overrides['RoomName'] ?? ''),
            'VenueName' => (string) ($overrides['VenueName'] ?? ''),
            'IsPublic' => !empty($overrides['IsPublic'] ?? 0),
        ]);

        $this->booking_service->shouldReceive( 'get_between' )
            ->once()
            ->andReturn( [ $booking ] );
    }

    // ── No buffers configured ─────────────────────────────────────────────────

    /** @test */
    public function returns_single_event_when_buffers_are_zero(): void {
        $this->stub_settings( 0, 0 );
        $this->wire_room_meta();
        $this->wire_booking( $this->single_booking() );

        Functions\when( 'myvh_setting' )->alias( static fn( $key, $default = null ) => match ( $key ) {
            'booking.buffers.set_up_minutes'               => 0,
            'booking.buffers.tidy_up_minutes'              => 0,
            'booking.buffers.show_buffer_times_separately' => false,
            'booking.buffers.buffer_text'                  => 'Buffer',
            default                                        => $default,
        } );

        $events = $this->service->get_events( '2026-06-01', '2026-06-02', 'admin' );

        $this->assertCount( 1, $events );
        $this->assertSame( 10, $events[0]['id'] );
        $this->assertSame( '2026-06-01T10:00:00', $events[0]['start'] );
        $this->assertSame( '2026-06-01T12:00:00', $events[0]['end'] );
        $this->assertArrayNotHasKey( 'actualStart', $events[0]['tags'] );
    }

    /** @test */
    public function returns_single_event_when_service_returns_booking_entities(): void {
        $this->stub_settings( 0, 0 );
        $this->wire_room_meta();
        $this->wire_booking_entity();

        $events = $this->service->get_events( '2026-06-01', '2026-06-02', 'admin' );

        $this->assertCount( 1, $events );
        $this->assertSame( 10, $events[0]['id'] );
        $this->assertSame( 'Community lunch', $events[0]['text'] );
        $this->assertSame( '2026-06-01T10:00:00', $events[0]['start'] );
        $this->assertSame( '2026-06-01T12:00:00', $events[0]['end'] );
    }

    // ── Merged display mode ───────────────────────────────────────────────────

    /** @test */
    public function merged_mode_extends_event_start_and_end_and_preserves_actual_times_in_tags(): void {
        $this->wire_room_meta( '08:00:00', '22:00:00' );
        $this->wire_booking( $this->single_booking() );

        Functions\when( 'myvh_setting' )->alias( static fn( $key, $default = null ) => match ( $key ) {
            'booking.buffers.set_up_minutes'               => 30,
            'booking.buffers.tidy_up_minutes'              => 15,
            'booking.buffers.show_buffer_times_separately' => false,
            'booking.buffers.buffer_text'                  => 'Buffer',
            default                                        => $default,
        } );

        $events = $this->service->get_events( '2026-06-01', '2026-06-02', 'admin' );

        $this->assertCount( 1, $events );
        $event = $events[0];

        // Visual start extended by 30 min, end by 15 min.
        $this->assertSame( '2026-06-01T09:30:00', $event['start'] );
        $this->assertSame( '2026-06-01T12:15:00', $event['end'] );

        // Actual booking times preserved in tags.
        $this->assertSame( '2026-06-01T10:00:00', $event['tags']['actualStart'] );
        $this->assertSame( '2026-06-01T12:00:00', $event['tags']['actualEnd'] );
    }

    // ── Separate display mode ─────────────────────────────────────────────────

    /** @test */
    public function separate_mode_appends_setup_and_tidy_buffer_events(): void {
        $this->wire_room_meta( '08:00:00', '22:00:00' );
        $this->wire_booking( $this->single_booking() );

        Functions\when( 'myvh_setting' )->alias( static fn( $key, $default = null ) => match ( $key ) {
            'booking.buffers.set_up_minutes'               => 30,
            'booking.buffers.tidy_up_minutes'              => 20,
            'booking.buffers.show_buffer_times_separately' => true,
            'booking.buffers.buffer_text'                  => 'Prep',
            default                                        => $default,
        } );

        $events = $this->service->get_events( '2026-06-01', '2026-06-02', 'admin' );

        $this->assertCount( 3, $events );

        // Booking event itself unchanged.
        $booking_event = $events[0];
        $this->assertSame( 10, $booking_event['id'] );
        $this->assertSame( '2026-06-01T10:00:00', $booking_event['start'] );
        $this->assertSame( '2026-06-01T12:00:00', $booking_event['end'] );
        $this->assertArrayNotHasKey( 'actualStart', $booking_event['tags'] );

        // Setup buffer event.
        $setup = $events[1];
        $this->assertSame( 'buffer_setup_10', $setup['id'] );
        $this->assertSame( 'Prep', $setup['text'] );
        $this->assertSame( '2026-06-01T09:30:00', $setup['start'] );
        $this->assertSame( '2026-06-01T10:00:00', $setup['end'] );
        $this->assertTrue( $setup['tags']['isBuffer'] );
        $this->assertSame( 'setup', $setup['tags']['bufferType'] );
        $this->assertSame( 'Main Hall', $setup['tags']['roomName'] );

        // Tidy buffer event.
        $tidy = $events[2];
        $this->assertSame( 'buffer_tidy_10', $tidy['id'] );
        $this->assertSame( '2026-06-01T12:00:00', $tidy['start'] );
        $this->assertSame( '2026-06-01T12:20:00', $tidy['end'] );
        $this->assertTrue( $tidy['tags']['isBuffer'] );
        $this->assertSame( 'tidy', $tidy['tags']['bufferType'] );
    }

    // ── Separate mode – only setup ────────────────────────────────────────────

    /** @test */
    public function separate_mode_only_appends_setup_event_when_tidy_is_zero(): void {
        $this->wire_room_meta();
        $this->wire_booking( $this->single_booking() );

        Functions\when( 'myvh_setting' )->alias( static fn( $key, $default = null ) => match ( $key ) {
            'booking.buffers.set_up_minutes'               => 30,
            'booking.buffers.tidy_up_minutes'              => 0,
            'booking.buffers.show_buffer_times_separately' => true,
            'booking.buffers.buffer_text'                  => 'Buffer',
            default                                        => $default,
        } );

        $events = $this->service->get_events( '2026-06-01', '2026-06-02', 'admin' );

        $this->assertCount( 2, $events );
        $this->assertSame( 'setup', $events[1]['tags']['bufferType'] );
    }

    // ── Opening boundary exemption in separate mode ───────────────────────────

    /** @test */
    public function setup_buffer_not_shown_when_booking_starts_at_room_opening(): void {
        // Room opens at 10:00; booking starts at 10:00 → no setup buffer.
        $this->wire_room_meta( '10:00:00', '22:00:00' );
        $this->wire_booking( $this->single_booking() );  // StartTime = '10:00:00'

        Functions\when( 'myvh_setting' )->alias( static fn( $key, $default = null ) => match ( $key ) {
            'booking.buffers.set_up_minutes'               => 30,
            'booking.buffers.tidy_up_minutes'              => 30,
            'booking.buffers.show_buffer_times_separately' => true,
            'booking.buffers.buffer_text'                  => 'Buffer',
            default                                        => $default,
        } );

        $events = $this->service->get_events( '2026-06-01', '2026-06-02', 'admin' );

        // 1 booking + 1 tidy buffer only.
        $this->assertCount( 2, $events );
        $buffer_types = array_column( array_column( array_slice( $events, 1 ), 'tags' ), 'bufferType' );
        $this->assertNotContains( 'setup', $buffer_types );
        $this->assertContains( 'tidy', $buffer_types );
    }

    // ── Closing boundary exemption in separate mode ───────────────────────────

    /** @test */
    public function tidy_buffer_not_shown_when_booking_ends_at_room_closing(): void {
        // Room closes at 12:00; booking ends at 12:00 → no tidy buffer.
        $this->wire_room_meta( '08:00:00', '12:00:00' );
        $this->wire_booking( $this->single_booking() );  // EndTime = '12:00:00'

        Functions\when( 'myvh_setting' )->alias( static fn( $key, $default = null ) => match ( $key ) {
            'booking.buffers.set_up_minutes'               => 30,
            'booking.buffers.tidy_up_minutes'              => 30,
            'booking.buffers.show_buffer_times_separately' => true,
            'booking.buffers.buffer_text'                  => 'Buffer',
            default                                        => $default,
        } );

        $events = $this->service->get_events( '2026-06-01', '2026-06-02', 'admin' );

        // 1 booking + 1 setup buffer only.
        $this->assertCount( 2, $events );
        $buffer_types = array_column( array_column( array_slice( $events, 1 ), 'tags' ), 'bufferType' );
        $this->assertContains( 'setup', $buffer_types );
        $this->assertNotContains( 'tidy', $buffer_types );
    }

    // ── Merged mode – opening boundary exemption ──────────────────────────────

    /** @test */
    public function merged_mode_does_not_extend_start_when_booking_starts_at_room_opening(): void {
        $this->wire_room_meta( '10:00:00', '22:00:00' );
        $this->wire_booking( $this->single_booking() );  // StartTime = '10:00:00'

        Functions\when( 'myvh_setting' )->alias( static fn( $key, $default = null ) => match ( $key ) {
            'booking.buffers.set_up_minutes'               => 30,
            'booking.buffers.tidy_up_minutes'              => 15,
            'booking.buffers.show_buffer_times_separately' => false,
            'booking.buffers.buffer_text'                  => 'Buffer',
            default                                        => $default,
        } );

        $events = $this->service->get_events( '2026-06-01', '2026-06-02', 'admin' );

        $this->assertCount( 1, $events );
        // Start unchanged; only end extended by 15 min.
        $this->assertSame( '2026-06-01T10:00:00', $events[0]['start'] );
        $this->assertSame( '2026-06-01T12:15:00', $events[0]['end'] );
        $this->assertSame( '2026-06-01T10:00:00', $events[0]['tags']['actualStart'] );
    }
}
