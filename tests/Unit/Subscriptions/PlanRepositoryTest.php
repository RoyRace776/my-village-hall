<?php

namespace {
    if (!class_exists('wpdb')) {
        class wpdb {}
    }

    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }
}

namespace MYVH\Tests\Unit\Subscriptions {

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Tests\Unit\UnitTestCase;

class PlanRepositoryTest extends UnitTestCase {
    /** @var \Mockery\MockInterface&\wpdb */
    private $wpdb;
    private PlanRepository $repository;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'sanitize_key' => fn($value) => strtolower((string) $value),
        ]);

        $this->wpdb = Mockery::mock('wpdb');
        $this->wpdb->base_prefix = 'wp_';

        $this->repository = new PlanRepository($this->wpdb);
    }

    /** @test */
    public function get_by_code_returns_plan_row(): void {
        $prepared_sql = 'SELECT * FROM wp_myvh_plans WHERE plan_key = basic LIMIT 1';

        $this->wpdb->shouldReceive('prepare')
            ->once()
            ->withArgs(function (string $sql, string $code): bool {
                return str_contains($sql, 'WHERE plan_key = %s')
                    && $code === 'basic';
            })
            ->andReturn($prepared_sql);

        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->with($prepared_sql, ARRAY_A)
            ->andReturnUsing(static fn(): array => ['id' => 1, 'plan_key' => 'basic', 'name' => 'Basic']);

        $plan = $this->repository->getByCode('BASIC');

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertSame('basic', $plan->getCode());
    }

    /** @test */
    public function get_by_code_returns_null_when_no_match(): void {
        $prepared_sql = 'SELECT * FROM wp_myvh_plans WHERE plan_key = missing LIMIT 1';

        $this->wpdb->shouldReceive('prepare')->once()->andReturn($prepared_sql);
        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->with($prepared_sql, ARRAY_A)
            ->andReturn(null);

        $this->assertNull($this->repository->getByCode('missing'));
    }

    /** @test */
    public function get_all_active_returns_rows(): void {
        $prepared_sql = 'SELECT * FROM wp_myvh_plans WHERE is_active = 1 ORDER BY id ASC';

        $this->wpdb->shouldReceive('prepare')
            ->once()
            ->withArgs(function (string $sql, int $active): bool {
                return str_contains($sql, 'WHERE is_active = %d') && $active === 1;
            })
            ->andReturn($prepared_sql);

        $this->wpdb->shouldReceive('get_results')
            ->once()
            ->with($prepared_sql, ARRAY_A)
            ->andReturn([
                ['id' => 1, 'plan_key' => 'basic'],
                ['id' => 2, 'plan_key' => 'standard'],
            ]);

        $plans = $this->repository->getAllActive();

        $this->assertCount(2, $plans);
        $this->assertInstanceOf(Plan::class, $plans[0]);
    }

    /** @test */
    public function get_all_active_returns_empty_array_when_query_returns_null(): void {
        $prepared_sql = 'SELECT * FROM wp_myvh_plans WHERE is_active = 1 ORDER BY id ASC';

        $this->wpdb->shouldReceive('prepare')->once()->andReturn($prepared_sql);
        $this->wpdb->shouldReceive('get_results')
            ->once()
            ->with($prepared_sql, ARRAY_A)
            ->andReturn(null);

        $this->assertSame([], $this->repository->getAllActive());
    }
}

} // end namespace
