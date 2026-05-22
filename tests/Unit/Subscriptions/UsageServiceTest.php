<?php

namespace MYVH\Tests\Unit\Subscriptions;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Repositories\UsageRepository;
use MYVH\Subscriptions\Services\UsageService;
use MYVH\Tests\Unit\UnitTestCase;

class UsageServiceTest extends UnitTestCase {
    /** @var UsageRepository&MockInterface */
    private $usage_repository;

    /** @var SubscriptionRepository&MockInterface */
    private $subscription_repository;

    /** @var PlanRepository&MockInterface */
    private $plan_repository;

    private UsageService $service;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'current_time' => fn($type = 'mysql') => $type === 'timestamp' ? 1716200000 : '2024-05-20 12:00:00',
            'sanitize_key' => fn($value) => strtolower((string) $value),
        ]);

        /** @var UsageRepository&MockInterface $usage_repository */
        $usage_repository = $this->mock(UsageRepository::class);
        $this->usage_repository = $usage_repository;

        /** @var SubscriptionRepository&MockInterface $subscription_repository */
        $subscription_repository = $this->mock(SubscriptionRepository::class);
        $this->subscription_repository = $subscription_repository;

        /** @var PlanRepository&MockInterface $plan_repository */
        $plan_repository = $this->mock(PlanRepository::class);
        $this->plan_repository = $plan_repository;

        $this->service = new UsageService(
            $this->usage_repository,
            $this->subscription_repository,
            $this->plan_repository
        );
    }

    /** @test */
    public function record_booking_increments_current_month_usage(): void {
        $this->usage_repository->shouldReceive('increment_month_quantity')
            ->once()
            ->with(10, 'bookings', '2024-05')
            ->andReturn(true);

        $this->assertTrue($this->service->recordBooking(10));
    }

    /** @test */
    public function get_current_usage_reads_current_month_quantity(): void {
        $this->usage_repository->shouldReceive('get_month_quantity')
            ->once()
            ->with(10, 'bookings', '2024-05')
            ->andReturn(27);

        $this->assertSame(27, $this->service->getCurrentUsage(10));
    }

    /** @test */
    public function exceeds_limit_returns_false_for_unlimited_null_limit(): void {
        $this->subscription_repository->shouldReceive('get_active_by_account_id')
            ->once()
            ->with(10)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray(['plan_code' => 'pro']));

        $this->plan_repository->shouldReceive('getByCode')
            ->once()
            ->with('pro')
            ->andReturnUsing(static fn(): Plan => Plan::fromArray([
                'metadata' => '{"features":{"booking_limit":null}}',
            ]));

        $this->usage_repository->shouldReceive('get_month_quantity')->never();

        $this->assertFalse($this->service->exceedsLimit(10));
    }

    /** @test */
    public function exceeds_limit_returns_true_when_current_usage_meets_limit(): void {
        $this->subscription_repository->shouldReceive('get_active_by_account_id')
            ->once()
            ->with(10)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray(['plan_code' => 'basic']));

        $this->plan_repository->shouldReceive('getByCode')
            ->once()
            ->with('basic')
            ->andReturnUsing(static fn(): Plan => Plan::fromArray([
                'metadata' => '{"features":{"booking_limit":30}}',
            ]));

        $this->usage_repository->shouldReceive('get_month_quantity')
            ->once()
            ->with(10, 'bookings', '2024-05')
            ->andReturn(30);

        $this->assertTrue($this->service->exceedsLimit(10));
    }

    /** @test */
    public function exceeds_limit_returns_false_when_usage_below_limit(): void {
        $this->subscription_repository->shouldReceive('get_active_by_account_id')
            ->once()
            ->with(10)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray(['plan_code' => 'standard']));

        $this->plan_repository->shouldReceive('getByCode')
            ->once()
            ->with('standard')
            ->andReturnUsing(static fn(): Plan => Plan::fromArray([
                'metadata' => '{"features":{"booking_limit":150}}',
            ]));

        $this->usage_repository->shouldReceive('get_month_quantity')
            ->once()
            ->with(10, 'bookings', '2024-05')
            ->andReturn(149);

        $this->assertFalse($this->service->exceedsLimit(10));
    }
}
