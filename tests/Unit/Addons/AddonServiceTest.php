<?php

namespace MYVH\Tests\Unit\Addons;

use MYVH\Addons\AddonRepository;
use MYVH\Addons\AddonService;
use MYVH\Bookings\BookingAddonRepository;
use MYVH\Bookings\BookingRepository;
use MYVH\Tests\Unit\UnitTestCase;

class AddonServiceTest extends UnitTestCase
{
    public function test_get_all_filters_explicitly_inactive_addons(): void
    {
        $repository = $this->mock(AddonRepository::class);
        $booking_addon_repository = $this->mock(BookingAddonRepository::class);
        $booking_repository = $this->mock(BookingRepository::class);

        $repository->shouldReceive('get_all_active')
            ->once()
            ->with(['orderby' => 'DisplayOrder'])
            ->andReturn([
                ['Id' => 1, 'Name' => 'Projector', 'IsActive' => 1],
                ['Id' => 2, 'Name' => 'Tea urn', 'IsActive' => 0],
            ]);

        $service = new AddonService($repository, $booking_addon_repository, $booking_repository);

        $result = $service->get_all(['orderby' => 'DisplayOrder']);

        $this->assertSame([
            ['Id' => 1, 'Name' => 'Projector', 'IsActive' => 1],
        ], $result);
    }

    public function test_get_all_keeps_addons_when_active_flag_is_missing(): void
    {
        $repository = $this->mock(AddonRepository::class);
        $booking_addon_repository = $this->mock(BookingAddonRepository::class);
        $booking_repository = $this->mock(BookingRepository::class);

        $repository->shouldReceive('get_all_active')
            ->once()
            ->with([])
            ->andReturn([
                ['Id' => 3, 'Name' => 'Stage lighting'],
            ]);

        $service = new AddonService($repository, $booking_addon_repository, $booking_repository);

        $result = $service->get_all();

        $this->assertSame([
            ['Id' => 3, 'Name' => 'Stage lighting'],
        ], $result);
    }
}