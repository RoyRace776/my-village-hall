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
        $this->service = new RoomRateService($this->repo, $this->customer_repo);
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