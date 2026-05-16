<?php

namespace MYVH\Tests\Unit\Availability;

use Brain\Monkey\Functions;
use MYVH\Tests\Unit\UnitTestCase;

class AvailabilityServiceTest extends UnitTestCase {

    private $booking_repo;
    private $room_repo;
    private $room_hours_repo;
    private $venue_repo;
    private $venue_hours_repo;
    private $service;

    protected function setUp(): void {
        parent::setUp();

        $this->booking_repo = $this->mock(\MYVH\Bookings\BookingRepository::class);
        $this->room_repo = $this->mock(\MYVH\Rooms\RoomRepository::class);
        $this->room_hours_repo = $this->mock(\MYVH\Rooms\RoomHoursRepository::class);
        $this->venue_repo = $this->mock(\MYVH\Venues\VenueRepository::class);
        $this->venue_hours_repo = $this->mock(\MYVH\Venues\VenueHoursRepository::class);

        $this->service = new \MYVH\Availability\AvailabilityService(
            $this->booking_repo,
            $this->room_repo,
            $this->room_hours_repo,
            $this->venue_repo,
            $this->venue_hours_repo
        );

        Functions\stubs([
            'myvh_setting' => static fn($key, $default = null) => $default,
            'wp_date' => static fn($format, $timestamp = null) => $timestamp === null ? date($format) : date($format, (int) $timestamp),
            'is_wp_error' => static fn($value) => $value instanceof \WP_Error,
        ]);
    }

    /** @test */
    public function booking_within_opening_hours_uses_legacy_room_hours_when_date_missing(): void {
        $this->room_repo->shouldReceive('get_by_id')
            ->once()
            ->with(5)
            ->andReturnUsing(static fn(): array => [
                'Id' => 5,
                'OpeningTime' => '09:00:00',
                'ClosingTime' => '17:00:00',
            ]);

        $result = $this->service->booking_within_opening_hours(5, '10:00:00', '12:00:00');

        $this->assertTrue($result);
    }

    /** @test */
    public function booking_within_opening_hours_respects_closed_day_using_daily_hours(): void {
        $this->room_repo->shouldReceive('get_by_id')
            ->times(2)
            ->with(5)
            ->andReturnUsing(static fn(): array => [
                'Id' => 5,
                'VenueId' => 3,
                'OpeningTime' => '09:00:00',
                'ClosingTime' => '17:00:00',
            ]);

        $this->room_hours_repo->shouldReceive('get_by_room')
            ->once()
            ->with(5)
            ->andReturn([
                ['DayOfWeek' => 2, 'UseVenueHours' => 1, 'IsClosed' => 0, 'OpeningTime' => null, 'ClosingTime' => null],
            ]);

        $this->venue_repo->shouldReceive('get_by_id')
            ->once()
            ->with(3)
            ->andReturnUsing(static fn(): array => [
                'Id' => 3,
                'OpeningTime' => '09:00:00',
                'ClosingTime' => '17:00:00',
            ]);

        $this->venue_hours_repo->shouldReceive('get_by_venue')
            ->once()
            ->with(3)
            ->andReturn([
                ['DayOfWeek' => 2, 'IsClosed' => 1, 'OpeningTime' => null, 'ClosingTime' => null],
            ]);

        $result = $this->service->booking_within_opening_hours(5, '10:00:00', '11:00:00', '2026-06-02', '2026-06-02');

        $this->assertFalse($result);
    }

    /** @test */
    public function room_opening_hours_by_day_allowed_fails_when_override_exceeds_venue_day_hours(): void {
        $this->venue_repo->shouldReceive('get_by_id')
            ->once()
            ->with(3)
            ->andReturnUsing(static fn(): array => [
                'Id' => 3,
                'OpeningTime' => '09:00:00',
                'ClosingTime' => '17:00:00',
            ]);

        $this->venue_hours_repo->shouldReceive('get_by_venue')
            ->once()
            ->with(3)
            ->andReturn([
                ['DayOfWeek' => 1, 'IsClosed' => 0, 'OpeningTime' => '10:00:00', 'ClosingTime' => '16:00:00'],
            ]);

        $result = $this->service->room_opening_hours_by_day_allowed([
            [
                'day_of_week' => 1,
                'use_venue_hours' => 0,
                'is_closed' => 0,
                'opening_time' => '09:00:00',
                'closing_time' => '16:00:00',
            ],
        ], 3);

        $this->assertFalse($result);
    }

    /** @test */
    public function next_available_slot_skips_closed_day_and_returns_slot_within_seven_days(): void {
        $start_date = '2026-06-01';
        $closed_day = (int) date('w', strtotime($start_date));

        $this->room_repo->shouldReceive('get_by_id')
            ->andReturn([
                'Id' => 5,
                'VenueId' => 2,
                'OpeningTime' => '09:00:00',
                'ClosingTime' => '11:00:00',
            ]);

        $this->room_hours_repo->shouldReceive('get_by_room')
            ->andReturn([
                ['DayOfWeek' => $closed_day, 'UseVenueHours' => 0, 'IsClosed' => 1, 'OpeningTime' => null, 'ClosingTime' => null],
            ]);

        $this->booking_repo->shouldReceive('has_conflict')
            ->andReturnUsing(static function ($room_id, $date, $start_time): bool {
                return !($date === '2026-06-02' && $start_time === '10:00:00');
            });

        $this->booking_repo->shouldReceive('has_conflict_in_buffer_window')
            ->andReturn(false);

        $result = $this->service->next_available_slot(5, $start_date, 60);

        $this->assertIsArray($result);
        $this->assertSame('2026-06-02', $result['date']);
        $this->assertSame('10:00', $result['start_time']);
        $this->assertSame('11:00', $result['end_time']);
    }

    /** @test */
    public function next_available_slot_honours_buffers_when_selecting_a_slot(): void {
        $this->room_repo->shouldReceive('get_by_id')
            ->andReturn([
                'Id' => 8,
                'VenueId' => 2,
                'OpeningTime' => '08:00:00',
                'ClosingTime' => '12:00:00',
            ]);

        $this->room_hours_repo->shouldReceive('get_by_room')
            ->andReturn([]);

        Functions\when('myvh_setting')->alias(static fn($key, $default = null) => match ($key) {
            'booking.set_up_minutes' => 30,
            'booking.tidy_up_minutes' => 30,
            default => $default,
        });

        $this->booking_repo->shouldReceive('has_conflict')
            ->andReturn(false);

        $this->booking_repo->shouldReceive('has_conflict_in_buffer_window')
            ->andReturnUsing(static function ($room_id, $window_start, $window_end): bool {
                return $window_start === '2026-06-02 08:00:00' && $window_end === '2026-06-02 09:30:00';
            });

        $result = $this->service->next_available_slot(8, '2026-06-02', 60);

        $this->assertIsArray($result);
        $this->assertSame('08:15', $result['start_time']);
        $this->assertSame('09:15', $result['end_time']);
    }

    /** @test */
    public function next_available_slot_returns_error_when_no_slot_exists_in_seven_days(): void {
        $this->room_repo->shouldReceive('get_by_id')
            ->andReturn([
                'Id' => 9,
                'VenueId' => 2,
                'OpeningTime' => '09:00:00',
                'ClosingTime' => '10:00:00',
            ]);

        $this->room_hours_repo->shouldReceive('get_by_room')
            ->andReturn([]);

        $this->booking_repo->shouldReceive('has_conflict')
            ->andReturn(true);

        $this->booking_repo->shouldReceive('has_conflict_in_buffer_window')
            ->andReturn(false);

        $result = $this->service->next_available_slot(9, '2026-06-02', 60);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('No available slot found in the next 7 days for the requested duration', $result->get_error_message());
    }
}
