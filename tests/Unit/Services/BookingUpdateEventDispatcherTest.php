<?php

namespace MYVH\Tests\Unit\Services;

use Brain\Monkey\Functions;
use MYVH\Bookings\BookingStatus;
use MYVH\Bookings\Services\BookingUpdateEventDispatcher;
use MYVH\Tests\Unit\UnitTestCase;

class BookingUpdateEventDispatcherTest extends UnitTestCase {
    private BookingUpdateEventDispatcher $dispatcher;

    protected function setUp(): void {
        parent::setUp();
        $this->dispatcher = new BookingUpdateEventDispatcher();
    }

    /** @test */
    public function dispatch_emits_confirmed_and_updated_when_status_moves_to_confirmed(): void {
        Functions\expect('do_action')
            ->once()
            ->with('myvh_event_booking.confirmed', \Mockery::on(static function (array $payload): bool {
                return ($payload['booking_id'] ?? 0) === 10 && ($payload['room_id'] ?? 0) === 4;
            }));

        Functions\expect('do_action')
            ->once()
            ->with('myvh_event_booking.updated', \Mockery::on(static function (array $payload): bool {
                return ($payload['booking_id'] ?? 0) === 10 && ($payload['room_id'] ?? 0) === 4;
            }));

        $this->dispatcher->dispatch([
            'booking_id' => 10,
            'room_id' => 4,
            'start_time' => '2026-06-01 10:00:00',
            'end_time' => '2026-06-01 11:00:00',
            'status' => BookingStatus::CONFIRMED->value,
        ], BookingStatus::PENDING->value);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function dispatch_emits_cancelled_and_updated_when_status_moves_to_cancelled(): void {
        Functions\expect('do_action')
            ->once()
            ->with('myvh_event_booking.cancelled', \Mockery::on(static function (array $payload): bool {
                return ($payload['booking_id'] ?? 0) === 11 && ($payload['room_id'] ?? 0) === 8;
            }));

        Functions\expect('do_action')
            ->once()
            ->with('myvh_event_booking.updated', \Mockery::on(static function (array $payload): bool {
                return ($payload['booking_id'] ?? 0) === 11 && ($payload['room_id'] ?? 0) === 8;
            }));

        $this->dispatcher->dispatch([
            'booking_id' => 11,
            'room_id' => 8,
            'start_time' => '2026-06-02 12:00:00',
            'end_time' => '2026-06-02 13:00:00',
            'status' => BookingStatus::CANCELLED->value,
        ], BookingStatus::CONFIRMED->value);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function dispatch_only_emits_updated_when_status_is_unchanged(): void {
        Functions\expect('do_action')
            ->once()
            ->with('myvh_event_booking.updated', \Mockery::on(static function (array $payload): bool {
                return ($payload['booking_id'] ?? 0) === 12 && ($payload['room_id'] ?? 0) === 9;
            }));

        $this->dispatcher->dispatch([
            'booking_id' => 12,
            'room_id' => 9,
            'start_time' => '2026-06-03 09:00:00',
            'end_time' => '2026-06-03 10:00:00',
            'status' => BookingStatus::CONFIRMED->value,
        ], BookingStatus::CONFIRMED->value);

        $this->addToAssertionCount(1);
    }
}
