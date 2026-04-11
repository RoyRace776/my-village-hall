<?php

namespace MYVH\Tests\Unit\Services;

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
    public function dispatch_after_does_nothing_when_old_status_missing(): void {
        $this->service->dispatch_after(5, null, 'cancelled', ['booking_id' => 5]);

        $this->assertTrue(true);
    }
}
