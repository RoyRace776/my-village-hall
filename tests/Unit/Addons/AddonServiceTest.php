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

    public function test_save_booking_addons_uses_chargeable_hours_for_hourly_addons(): void
    {
        $repository = new class extends AddonRepository {
            public function __construct()
            {
            }

            public function get_by_id($id): ?array
            {
                return [
                    'Id' => 7,
                    'ChargeType' => 'per_hour',
                ];
            }
        };

        $booking_addon_repository = $this->mock(BookingAddonRepository::class);
        $booking_repository = new class extends BookingRepository {
            public function __construct()
            {
            }

            public function get_by_id($id): ?array
            {
                return [
                    'Id' => 15,
                    'StartDate' => '2026-06-01',
                    'StartTime' => '09:00:00',
                    'EndDate' => '2026-06-01',
                    'EndTime' => '11:30:00',
                    'ChargeableHours' => '1.00',
                ];
            }
        };

        $booking_addon_repository->shouldReceive('create')
            ->once()
            ->with(
                \Mockery::on(static function (array $record): bool {
                    return $record['BookingId'] === 15
                        && $record['AddonId'] === 7
                        && $record['Quantity'] === 1.0
                        && $record['UnitPrice'] === 12.5
                        && $record['TotalAmount'] === 12.5;
                })
            );

        $service = new AddonService($repository, $booking_addon_repository, $booking_repository);

        $service->save_booking_addons(15, [[
            'addon_id' => 7,
            'unit_price' => 12.5,
            'description' => 'Hourly projector',
        ]]);

        $this->addToAssertionCount(1);
    }

    public function test_save_booking_addons_prefers_supplied_quantity_for_hourly_addons(): void
    {
        $repository = new class extends AddonRepository {
            public function __construct()
            {
            }

            public function get_by_id($id): ?array
            {
                return [
                    'Id' => 7,
                    'ChargeType' => 'per_hour',
                ];
            }
        };

        $booking_addon_repository = $this->mock(BookingAddonRepository::class);
        $booking_repository = new class extends BookingRepository {
            public function __construct()
            {
            }

            public function get_by_id($id): ?array
            {
                return [
                    'Id' => 15,
                    'ChargeableHours' => '1.00',
                ];
            }
        };

        $booking_addon_repository->shouldReceive('create')
            ->once()
            ->with(
                \Mockery::on(static function (array $record): bool {
                    return $record['BookingId'] === 15
                        && $record['AddonId'] === 7
                        && $record['Quantity'] === 3.5
                        && $record['UnitPrice'] === 12.5
                        && $record['TotalAmount'] === 43.75;
                })
            );

        $service = new AddonService($repository, $booking_addon_repository, $booking_repository);

        $service->save_booking_addons(15, [[
            'addon_id' => 7,
            'quantity' => 3.5,
            'unit_price' => 12.5,
            'description' => 'Hourly projector',
        ]]);

        $this->addToAssertionCount(1);
    }
}