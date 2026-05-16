<?php

namespace MYVH\Tests\Unit\Pricing;

use MYVH\Customers\CustomerRepository;
use MYVH\Pricing\RoomRateRepository;
use MYVH\Pricing\RoomRateService;
use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class RoomRateServiceTest extends UnitTestCase
{
    /** @var RoomRateRepository&\Mockery\MockInterface */
    private $repo;

    /** @var CustomerRepository&\Mockery\MockInterface */
    private $customer_repo;

    private RoomRateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = $this->mock(RoomRateRepository::class);
        $this->customer_repo = $this->mock(CustomerRepository::class);
        $this->service = new RoomRateService($this->repo);
    }

    /** @test */
    public function save_rejects_missing_minimum_hours(): void
    {
        $result = $this->service->save([
            'room_id' => 1,
            'name' => 'Standard Rate',
            'charge_type' => 'per_hour',
            'rate' => 12.5,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('Minimum hours must be greater than zero', $result->get_error_message());
    }

    /** @test */
    public function save_persists_positive_minimum_hours(): void
    {
        $this->repo->shouldReceive('create')
            ->once()
            ->withArgs(static function (array $record): bool {
                return $record['MinimumHours'] === 2.0
                    && $record['RoomId'] === 1
                    && $record['Name'] === 'Standard Rate';
            })
            ->andReturn(44);
                $this->repo->shouldReceive('replace_days_for_rate')
                    ->once()
                    ->with(44, [])
                    ->andReturn(true);

        $result = $this->service->save([
            'room_id' => 1,
            'name' => 'Standard Rate',
            'charge_type' => 'per_hour',
            'rate' => 12.5,
            'minimum_hours' => 2,
        ]);

        $this->assertSame(44, $result);
    }

    /** @test */
    public function save_rejects_fixed_rate_with_schedule_fields(): void
    {
        $result = $this->service->save([
            'room_id' => 1,
            'name' => 'Fixed Morning Rate',
            'charge_type' => 'fixed',
            'rate' => 25,
            'minimum_hours' => 1,
            'day_of_week' => '1',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('Fixed rates cannot be limited to a day/time schedule', $result->get_error_message());
    }

    /** @test */
    public function save_persists_schedule_fields_for_hourly_rate(): void
    {
        $this->repo->shouldReceive('create')
            ->once()
            ->withArgs(static function (array $record): bool {
                return $record['DayOfWeek'] === 3
                    && $record['StartTime'] === '09:00:00'
                    && $record['EndTime'] === '17:00:00'
                    && $record['ChargeType'] === 'per_hour';
            })
            ->andReturn(88);
                $this->repo->shouldReceive('replace_days_for_rate')
                    ->once()
                    ->with(88, [3])
                    ->andReturn(true);

        $result = $this->service->save([
            'room_id' => 1,
            'name' => 'Wednesday Daytime',
            'charge_type' => 'per_hour',
            'rate' => 18,
            'minimum_hours' => 1,
            'day_of_week' => '3',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $this->assertSame(88, $result);
    }

    /** @test */
    public function save_persists_multiple_days_and_clears_legacy_day_column(): void
    {
        $this->repo->shouldReceive('create')
            ->once()
            ->withArgs(static function (array $record): bool {
                return $record['DayOfWeek'] === null
                    && $record['StartTime'] === '09:00:00'
                    && $record['EndTime'] === '17:00:00'
                    && $record['ChargeType'] === 'per_hour';
            })
            ->andReturn(99);
        $this->repo->shouldReceive('replace_days_for_rate')
            ->once()
            ->with(99, [1, 2, 3, 4, 5])
            ->andReturn(true);

        $result = $this->service->save([
            'room_id' => 1,
            'name' => 'Weekday Daytime',
            'charge_type' => 'per_hour',
            'rate' => 18,
            'minimum_hours' => 1,
            'days_of_week' => ['1', '2', '3', '4', '5'],
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $this->assertSame(99, $result);
    }

    /** @test */
    public function get_all_orders_by_priority_descending(): void
    {
        $this->repo->shouldReceive('get_all')
            ->once()
            ->with([
                'orderby' => 'Priority',
                'order' => 'DESC',
            ])
            ->andReturn([
                ['Id' => 2, 'Priority' => 10],
                ['Id' => 1, 'Priority' => 1],
            ]);

        $result = $this->service->get_all();

        $this->assertSame(10, $result[0]['Priority']);
        $this->assertSame(1, $result[1]['Priority']);
    }
}