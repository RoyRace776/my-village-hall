<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Tests\Unit\UnitTestCase;

class BookingCreationEventDispatcherTest extends UnitTestCase {
    private $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new \MYVH\Bookings\Services\BookingCreationEventDispatcher();
    }

    /** @test */
    public function dispatch_created_accepts_confirmed_status_without_errors(): void {
        $this->service->dispatch_created(10, [
            'room_id' => 5,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'status' => 'confirmed',
        ]);

        $this->assertTrue(true);
    }

    /** @test */
    public function dispatch_after_create_accepts_payload_without_errors(): void {
        $this->service->dispatch_after_create(11, [
            'room_id' => 6,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'status' => 'pending',
        ]);

        $this->assertTrue(true);
    }
}
