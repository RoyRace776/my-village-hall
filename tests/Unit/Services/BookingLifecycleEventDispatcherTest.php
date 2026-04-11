<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Tests\Unit\UnitTestCase;

class BookingLifecycleEventDispatcherTest extends UnitTestCase {
    private $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new \MYVH\Bookings\Services\BookingLifecycleEventDispatcher();
    }

    /** @test */
    public function dispatch_before_save_accepts_create_payload_without_errors(): void {
        $this->service->dispatch_before_save([
            'booking_id' => 0,
            'room_id' => 5,
        ]);

        $this->assertTrue(true);
    }

    /** @test */
    public function dispatch_before_save_accepts_update_payload_without_errors(): void {
        $this->service->dispatch_before_save([
            'booking_id' => 12,
            'room_id' => 5,
        ]);

        $this->assertTrue(true);
    }

    /** @test */
    public function dispatch_after_update_accepts_payload_without_errors(): void {
        $this->service->dispatch_after_update([
            'booking_id' => 12,
            'status' => 'confirmed',
        ]);

        $this->assertTrue(true);
    }
}
