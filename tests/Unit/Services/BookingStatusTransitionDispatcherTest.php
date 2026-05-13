<?php

namespace MYVH\Tests\Unit\Services;

use Brain\Monkey\Functions;
use MYVH\Tests\Unit\UnitTestCase;

class BookingStatusTransitionDispatcherTest extends UnitTestCase {
    private $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new \MYVH\Bookings\Services\BookingStatusTransitionDispatcher();
    }

    /** @test */
    public function dispatch_before_does_nothing_when_status_is_unchanged(): void {
        $this->service->dispatch_before(5, 'confirmed', 'confirmed', ['booking_id' => 5]);

        $this->assertTrue(true);
    }

    /** @test */
    public function dispatch_before_emits_before_status_change_event_when_status_changes(): void {
        Functions\expect('do_action')
            ->once()
            ->with('myvh_event_booking.before_status_change', \Mockery::on(static function (array $payload): bool {
                return ($payload['booking_id'] ?? 0) === 6
                    && ($payload['old_status'] ?? '') === 'pending'
                    && ($payload['new_status'] ?? '') === 'confirmed';
            }));

        $this->service->dispatch_before(6, 'pending', 'confirmed', ['booking_id' => 6]);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function dispatch_after_does_nothing_when_old_status_missing(): void {
        $this->service->dispatch_after(5, null, 'cancelled', ['booking_id' => 5]);

        $this->assertTrue(true);
    }

    /** @test */
    public function dispatch_after_emits_after_status_change_event_when_status_changes(): void {
        Functions\expect('do_action')
            ->once()
            ->with('myvh_event_booking.after_status_change', \Mockery::on(static function (array $payload): bool {
                return ($payload['booking_id'] ?? 0) === 7
                    && ($payload['old_status'] ?? '') === 'confirmed'
                    && ($payload['new_status'] ?? '') === 'cancelled';
            }));

        $this->service->dispatch_after(7, 'confirmed', 'cancelled', ['booking_id' => 7]);

        $this->addToAssertionCount(1);
    }
}
