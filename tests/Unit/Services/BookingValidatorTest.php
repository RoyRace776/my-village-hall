<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class BookingValidatorTest extends UnitTestCase {

    private $booking_repo;
    private $customer_repo;
    private $organisation_repo;
    private $room_service;
    private $availability;
    private $pricing;
    private $room_rate_service;
    private $room_rules;
    private $validator;

    protected function setUp(): void {
        parent::setUp();

        $this->booking_repo = $this->mock(\MYVH\Bookings\BookingRepository::class);
        $this->customer_repo = $this->mock(\MYVH\Customers\CustomerRepository::class);
        $this->organisation_repo = $this->mock(\MYVH\Organisations\OrganisationRepository::class);
        $this->room_service = $this->mock(\MYVH\Rooms\RoomService::class);
        $this->availability = $this->mock(\MYVH\Availability\AvailabilityService::class);
        $this->pricing = $this->mock(\MYVH\Pricing\PricingService::class);
        $this->room_rate_service = $this->mock(\MYVH\Pricing\RoomRateService::class);
        $this->room_rules = $this->mock(\MYVH\Rooms\RoomRulesService::class);

        $this->validator = new \MYVH\Bookings\BookingValidator(
            $this->booking_repo,
            $this->customer_repo,
            $this->organisation_repo,
            $this->room_service,
            $this->availability,
            $this->pricing,
            $this->room_rate_service,
            $this->room_rules
        );
    }

    private function valid_data(array $overrides = []): array {
        return array_merge([
            'customer_id' => 11,
            'organisation_id' => 0,
            'room_id' => 5,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ], $overrides);
    }

    private function wire_common_success_path(): void {
        $this->customer_repo->shouldReceive('get_by_id')
            ->once()
            ->with(11)
            ->andReturnUsing(static fn(): array => ['Id' => 11]);

        $room = [
            'Id' => 5,
            'AllowMultiDayBookings' => 1,
            'OpeningTime' => '08:00:00',
            'ClosingTime' => '22:00:00',
        ];

        $this->room_service->shouldReceive('get')
            ->once()
            ->with(5)
            ->andReturnUsing(static fn() => $room);

        $this->room_rules->shouldReceive('is_duration_allowed')->once()->andReturn(true);
        $this->room_rules->shouldReceive('is_day_allowed')->once()->andReturn(true);
        $this->room_rules->shouldReceive('has_buffer_time')->once()->andReturn(true);

        $this->availability->shouldReceive('booking_within_opening_hours')->once()->andReturn(true);
        $this->availability->shouldReceive('room_is_available')->once()->andReturn(true);
    }

    /** @test */
    public function validate_returns_error_when_room_has_no_rate(): void {
        $this->wire_common_success_path();

        $this->room_rate_service->shouldReceive('get_booking_rate')
            ->once()
            ->with(5, ['Id' => 11], [])
            ->andReturn(null);

        $result = $this->validator->validate($this->valid_data());

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('This room cannot be booked because no applicable room rate is configured.', $result->get_error_message());
    }

    /** @test */
    public function validate_returns_true_when_room_rate_exists(): void {
        $this->wire_common_success_path();

        $this->room_rate_service->shouldReceive('get_booking_rate')
            ->once()
            ->with(5, ['Id' => 11], [])
            ->andReturnUsing(static fn(): array => ['Id' => 90]);

        $result = $this->validator->validate($this->valid_data());

        $this->assertTrue($result);
    }

    /** @test */
    public function validate_returns_error_when_start_time_is_not_on_a_fifteen_minute_boundary(): void {
        $result = $this->validator->validate($this->valid_data([
            'start_time' => '09:10:00',
        ]));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('Bookings must start and end on 15 minute intervals', $result->get_error_message());
    }

    /** @test */
    public function validate_returns_error_when_end_time_is_not_on_a_fifteen_minute_boundary(): void {
        $result = $this->validator->validate($this->valid_data([
            'end_time' => '11:07:00',
        ]));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('Bookings must start and end on 15 minute intervals', $result->get_error_message());
    }

    /** @test */
    public function validate_returns_error_when_buffer_time_is_insufficient(): void {
        $this->customer_repo->shouldReceive('get_by_id')
            ->once()
            ->with(11)
            ->andReturnUsing(static fn(): array => ['Id' => 11]);

        $room = [
            'Id' => 5,
            'AllowMultiDayBookings' => 1,
            'OpeningTime' => '08:00:00',
            'ClosingTime' => '22:00:00',
        ];

        $this->room_service->shouldReceive('get')
            ->once()
            ->with(5)
            ->andReturnUsing(static fn() => $room);

        $this->room_rules->shouldReceive('is_duration_allowed')->once()->andReturn(true);
        $this->room_rules->shouldReceive('is_day_allowed')->once()->andReturn(true);
        $this->room_rules->shouldReceive('has_buffer_time')
            ->once()
            ->withArgs(static fn($r, $start, $end, $exclude) => $exclude === null)
            ->andReturn(false);

        $result = $this->validator->validate($this->valid_data());

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('Not enough buffer time for this booking', $result->get_error_message());
    }

    /** @test */
    public function validate_forwards_booking_id_to_has_buffer_time_on_update(): void {
        $data = $this->valid_data(['booking_id' => 77]);

        $this->booking_repo->shouldReceive('get_by_id')
            ->once()
            ->with(77)
            ->andReturnUsing(static fn(): array => ['Id' => 77, 'Status' => 'confirmed']);

        $this->customer_repo->shouldReceive('get_by_id')
            ->once()
            ->with(11)
            ->andReturnUsing(static fn(): array => ['Id' => 11]);

        $room = [
            'Id' => 5,
            'AllowMultiDayBookings' => 1,
            'OpeningTime' => '08:00:00',
            'ClosingTime' => '22:00:00',
        ];

        $this->room_service->shouldReceive('get')
            ->once()
            ->with(5)
            ->andReturnUsing(static fn() => $room);

        $this->room_rules->shouldReceive('is_duration_allowed')->once()->andReturn(true);
        $this->room_rules->shouldReceive('is_day_allowed')->once()->andReturn(true);
        $this->room_rules->shouldReceive('has_buffer_time')
            ->once()
            ->withArgs(static fn($r, $start, $end, $exclude) => $exclude === 77)
            ->andReturn(true);

        $this->availability->shouldReceive('booking_within_opening_hours')->once()->andReturn(true);
        $this->availability->shouldReceive('room_is_available')->once()->andReturn(true);

        $this->room_rate_service->shouldReceive('get_booking_rate')
            ->once()
            ->andReturnUsing(static fn(): array => ['Id' => 90]);

        $result = $this->validator->validate($data);

        $this->assertTrue($result);
    }

    /** @test */
    public function validate_blocks_cancelled_to_pending_when_room_has_no_space(): void {
        $data = $this->valid_data([
            'booking_id' => 77,
            'status' => 'pending',
        ]);

        $this->booking_repo->shouldReceive('get_by_id')
            ->once()
            ->with(77)
            ->andReturnUsing(static fn(): array => ['Id' => 77, 'Status' => 'cancelled']);

        $this->customer_repo->shouldReceive('get_by_id')
            ->once()
            ->with(11)
            ->andReturnUsing(static fn(): array => ['Id' => 11]);

        $room = [
            'Id' => 5,
            'AllowMultiDayBookings' => 1,
            'OpeningTime' => '08:00:00',
            'ClosingTime' => '22:00:00',
        ];

        $this->room_service->shouldReceive('get')
            ->once()
            ->with(5)
            ->andReturnUsing(static fn() => $room);

        $this->room_rules->shouldReceive('is_duration_allowed')->once()->andReturn(true);
        $this->room_rules->shouldReceive('is_day_allowed')->once()->andReturn(true);
        $this->room_rules->shouldReceive('has_buffer_time')->once()->andReturn(true);

        $this->availability->shouldReceive('booking_within_opening_hours')->once()->andReturn(true);
        $this->availability->shouldReceive('room_is_available')->once()->andReturn(false);

        $result = $this->validator->validate($data);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('The room no longer has space for this booking, so the status cannot be changed.', $result->get_error_message());
    }

    /** @test */
    public function validate_blocks_cancelled_to_confirmed_when_room_has_no_space(): void {
        $data = $this->valid_data([
            'booking_id' => 77,
            'status' => 'confirmed',
        ]);

        $this->booking_repo->shouldReceive('get_by_id')
            ->once()
            ->with(77)
            ->andReturnUsing(static fn(): array => ['Id' => 77, 'Status' => 'cancelled']);

        $this->customer_repo->shouldReceive('get_by_id')
            ->once()
            ->with(11)
            ->andReturnUsing(static fn(): array => ['Id' => 11]);

        $room = [
            'Id' => 5,
            'AllowMultiDayBookings' => 1,
            'OpeningTime' => '08:00:00',
            'ClosingTime' => '22:00:00',
        ];

        $this->room_service->shouldReceive('get')
            ->once()
            ->with(5)
            ->andReturnUsing(static fn() => $room);

        $this->room_rules->shouldReceive('is_duration_allowed')->once()->andReturn(true);
        $this->room_rules->shouldReceive('is_day_allowed')->once()->andReturn(true);
        $this->room_rules->shouldReceive('has_buffer_time')->once()->andReturn(true);

        $this->availability->shouldReceive('booking_within_opening_hours')->once()->andReturn(true);
        $this->availability->shouldReceive('room_is_available')->once()->andReturn(false);

        $result = $this->validator->validate($data);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('The room no longer has space for this booking, so the status cannot be changed.', $result->get_error_message());
    }

    /** @test */
    public function validate_blocks_cancelled_to_completed_when_room_has_no_space(): void {
        $data = $this->valid_data([
            'booking_id' => 77,
            'status' => 'completed',
        ]);

        $this->booking_repo->shouldReceive('get_by_id')
            ->once()
            ->with(77)
            ->andReturnUsing(static fn(): array => ['Id' => 77, 'Status' => 'cancelled']);

        $this->customer_repo->shouldReceive('get_by_id')
            ->once()
            ->with(11)
            ->andReturnUsing(static fn(): array => ['Id' => 11]);

        $room = [
            'Id' => 5,
            'AllowMultiDayBookings' => 1,
            'OpeningTime' => '08:00:00',
            'ClosingTime' => '22:00:00',
        ];

        $this->room_service->shouldReceive('get')
            ->once()
            ->with(5)
            ->andReturnUsing(static fn() => $room);

        $this->room_rules->shouldReceive('is_duration_allowed')->once()->andReturn(true);
        $this->room_rules->shouldReceive('is_day_allowed')->once()->andReturn(true);
        $this->room_rules->shouldReceive('has_buffer_time')->once()->andReturn(true);

        $this->availability->shouldReceive('booking_within_opening_hours')->once()->andReturn(true);
        $this->availability->shouldReceive('room_is_available')->once()->andReturn(false);

        $result = $this->validator->validate($data);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('The room no longer has space for this booking, so the status cannot be changed.', $result->get_error_message());
    }

    /** @test */
    public function validate_allows_cancelled_to_confirmed_when_room_has_space(): void {
        $data = $this->valid_data([
            'booking_id' => 77,
            'status' => 'confirmed',
        ]);

        $this->booking_repo->shouldReceive('get_by_id')
            ->once()
            ->with(77)
            ->andReturnUsing(static fn(): array => ['Id' => 77, 'Status' => 'cancelled']);

        $this->customer_repo->shouldReceive('get_by_id')
            ->once()
            ->with(11)
            ->andReturnUsing(static fn(): array => ['Id' => 11]);

        $room = [
            'Id' => 5,
            'AllowMultiDayBookings' => 1,
            'OpeningTime' => '08:00:00',
            'ClosingTime' => '22:00:00',
        ];

        $this->room_service->shouldReceive('get')
            ->once()
            ->with(5)
            ->andReturnUsing(static fn() => $room);

        $this->room_rules->shouldReceive('is_duration_allowed')->once()->andReturn(true);
        $this->room_rules->shouldReceive('is_day_allowed')->once()->andReturn(true);
        $this->room_rules->shouldReceive('has_buffer_time')->once()->andReturn(true);

        $this->availability->shouldReceive('booking_within_opening_hours')->once()->andReturn(true);
        $this->availability->shouldReceive('room_is_available')->once()->andReturn(true);

        $this->room_rate_service->shouldReceive('get_booking_rate')
            ->once()
            ->andReturnUsing(static fn(): array => ['Id' => 90]);

        $result = $this->validator->validate($data);

        $this->assertTrue($result);
    }
}
