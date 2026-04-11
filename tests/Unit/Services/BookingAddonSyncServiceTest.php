<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Tests\Unit\UnitTestCase;

class BookingAddonSyncServiceTest extends UnitTestCase {
    private $addon_service;
    private $service;

    protected function setUp(): void {
        parent::setUp();

        $this->addon_service = $this->mock(\MYVH\Addons\AddonService::class);
        $this->service = new \MYVH\Bookings\Services\BookingAddonSyncService($this->addon_service);
    }

    /** @test */
    public function sync_calls_addon_service_with_replace_false(): void {
        $addons = [
            ['addon_id' => 10, 'quantity' => 1],
        ];

        $this->addon_service->shouldReceive('save_booking_addons')
            ->with(5, $addons, false)
            ->once();

        $this->service->sync(5, $addons, false);

        $this->assertTrue(true);
    }

    /** @test */
    public function sync_calls_addon_service_with_replace_true(): void {
        $addons = [
            ['addon_id' => 20, 'quantity' => 2],
        ];

        $this->addon_service->shouldReceive('save_booking_addons')
            ->with(7, $addons, true)
            ->once();

        $this->service->sync(7, $addons, true);

        $this->assertTrue(true);
    }
}
