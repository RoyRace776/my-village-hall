<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Deposits;

use DateTime;
use MYVH\Deposits\DepositService;
use MYVH\Rooms\RoomDepositRepository;
use MYVH\Tests\Unit\UnitTestCase;

class DepositServiceTest extends UnitTestCase {
    private $repository;
    private DepositService $service;

    protected function setUp(): void {
        parent::setUp();

        $this->repository = $this->mock(RoomDepositRepository::class);
        $this->service = new DepositService($this->repository);
    }

    /** @test */
    public function evaluate_returns_null_when_deposits_are_disabled(): void {
        $this->repository->shouldReceive('get')
            ->once()
            ->with(4)
            ->andReturn([
                'enabled' => false,
                'days' => [],
                'end_after' => null,
                'amount' => 20.0,
                'action' => 'auto_add',
            ]);

        $result = $this->service->evaluate(4, new DateTime('2026-05-08 19:00:00'));

        $this->assertNull($result);
    }

    /** @test */
    public function evaluate_returns_null_when_amount_is_zero(): void {
        $this->repository->shouldReceive('get')
            ->once()
            ->with(9)
            ->andReturn([
                'enabled' => true,
                'days' => [],
                'end_after' => null,
                'amount' => 0.0,
                'action' => 'auto_add',
            ]);

        $result = $this->service->evaluate(9, new DateTime('2026-05-08 19:00:00'));

        $this->assertNull($result);
    }

    /** @test */
    public function evaluate_applies_day_and_time_rules(): void {
        $this->repository->shouldReceive('get')
            ->twice()
            ->with(10)
            ->andReturn([
                'enabled' => true,
                'days' => ['fri', 'sat'],
                'end_after' => '18:00',
                'amount' => 50.0,
                'action' => 'auto_add',
            ]);

        $before_cutoff = $this->service->evaluate(10, new DateTime('2026-05-08 17:30:00')); // Friday
        $after_cutoff = $this->service->evaluate(10, new DateTime('2026-05-08 18:30:00')); // Friday

        $this->assertNull($before_cutoff);
        $this->assertSame([
            'amount' => 50.0,
            'action' => 'auto_add',
        ], $after_cutoff);
    }

    /** @test */
    public function evaluate_matches_all_days_when_no_days_are_configured(): void {
        $this->repository->shouldReceive('get')
            ->once()
            ->with(12)
            ->andReturn([
                'enabled' => true,
                'days' => [],
                'end_after' => null,
                'amount' => 15.0,
                'action' => 'require_review',
            ]);

        $result = $this->service->evaluate(12, new DateTime('2026-05-10 09:00:00'));

        $this->assertSame([
            'amount' => 15.0,
            'action' => 'require_review',
        ], $result);
    }
}
