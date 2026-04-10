<?php

namespace MYVH\Tests\Unit\Availability;

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
    }

    /** @test */
    public function booking_within_opening_hours_uses_legacy_room_hours_when_date_missing(): void {
        $this->room_repo->shouldReceive('get_by_id')
            ->once()
            ->with(5)
            ->andReturn([
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
            ->andReturn([
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
            ->andReturn([
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
            ->andReturn([
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
}
