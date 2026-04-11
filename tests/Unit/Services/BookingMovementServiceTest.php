<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class BookingMovementServiceTest extends UnitTestCase {
    private $room_service;
    private $availability;
    private $booking_repo;
    private $service;

    protected function setUp(): void {
        parent::setUp();

        $this->room_service = $this->mock(\MYVH\Rooms\RoomService::class);
        $this->availability = $this->mock(\MYVH\Availability\AvailabilityService::class);
        $this->booking_repo = $this->mock(\MYVH\Bookings\BookingRepository::class);

        $this->service = new \MYVH\Bookings\Services\BookingMovementService(
            $this->room_service,
            $this->availability,
            $this->booking_repo
        );
    }

    /** @test */
    public function move_booking_returns_wp_error_when_room_not_found(): void {
        $this->room_service->shouldReceive('get')->with(99)->once()->andReturn(null);

        $result = $this->service->move_booking(1, '2026-06-01T09:00:00', '2026-06-01T11:00:00', 99);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function move_booking_returns_wp_error_when_room_is_not_available(): void {
        $this->room_service->shouldReceive('get')->with(5)->once()->andReturn([
            'AllowMultiDayBookings' => 0,
            'OpeningTime' => '08:00:00',
            'ClosingTime' => '22:00:00',
        ]);

        $this->availability->shouldReceive('booking_within_opening_hours')->once()->andReturn(true);
        $this->availability->shouldReceive('room_is_available')->once()->andReturn(false);

        $result = $this->service->move_booking(1, '2026-06-01T09:00:00', '2026-06-01T11:00:00', 5);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function move_booking_returns_1_on_success(): void {
        $this->room_service->shouldReceive('get')->with(5)->once()->andReturn([
            'AllowMultiDayBookings' => 0,
            'OpeningTime' => '08:00:00',
            'ClosingTime' => '22:00:00',
        ]);

        $this->availability->shouldReceive('booking_within_opening_hours')->once()->andReturn(true);
        $this->availability->shouldReceive('room_is_available')->once()->andReturn(true);

        $this->booking_repo->shouldReceive('move_booking')->once()->andReturn(1);

        $result = $this->service->move_booking(1, '2026-06-01T09:00:00', '2026-06-01T11:00:00', 5);

        $this->assertSame(1, $result);
    }
}
