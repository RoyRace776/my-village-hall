<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Subscriptions;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\PlanChangePolicyService;
use MYVH\Tests\Unit\UnitTestCase;

class PlanChangePolicyServiceTest extends UnitTestCase {
    /** @var PlanRepository&MockInterface */
    private $plan_repository;

    /** @var SubscriptionRepository&MockInterface */
    private $subscription_repository;

    private PlanChangePolicyService $service;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            '__' => fn($value) => (string) $value,
            'sanitize_key' => fn($value) => strtolower((string) $value),
        ]);

        /** @var PlanRepository&MockInterface $plan_repository */
        $plan_repository = $this->mock(PlanRepository::class);
        $this->plan_repository = $plan_repository;

        /** @var SubscriptionRepository&MockInterface $subscription_repository */
        $subscription_repository = $this->mock(SubscriptionRepository::class);
        $this->subscription_repository = $subscription_repository;

        $this->service = new PlanChangePolicyService(
            $this->plan_repository,
            $this->subscription_repository
        );
    }

    /** @test */
    public function can_change_to_plan_blocks_downgrade_to_trial(): void {
        $trial = Plan::fromArray([
            'id' => 1,
            'plan_key' => 'trial',
            'price' => 0,
            'metadata' => json_encode(['features' => ['bookings' => 10]]),
        ]);

        $this->subscription_repository->shouldReceive('get_active_by_account_id')
            ->once()
            ->with(11)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'id' => 100,
                'plan_code' => 'pro',
            ]));

        $this->plan_repository->shouldReceive('getByCode')
            ->once()
            ->with('pro')
            ->andReturnUsing(static fn(): Plan => Plan::fromArray([
                'id' => 3,
                'plan_key' => 'pro',
                'price' => 29,
                'metadata' => json_encode(['features' => ['bookings' => null]]),
            ]));

        $decision = $this->service->canChangeToPlan(11, $trial);

        $this->assertFalse((bool) ($decision['allowed'] ?? true));
        $this->assertTrue((bool) ($decision['is_downgrade'] ?? false));
        $this->assertSame('You cannot downgrade to the Trial plan.', $decision['message'] ?? '');
    }

    /** @test */
    public function can_change_to_plan_allows_upgrade_to_paid_plan(): void {
        $pro = Plan::fromArray([
            'id' => 3,
            'plan_key' => 'pro',
            'price' => 29,
            'metadata' => json_encode(['features' => ['bookings' => null]]),
        ]);

        $this->subscription_repository->shouldReceive('get_active_by_account_id')
            ->once()
            ->with(12)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'id' => 101,
                'plan_code' => 'trial',
            ]));

        $this->plan_repository->shouldReceive('getByCode')
            ->once()
            ->with('trial')
            ->andReturnUsing(static fn(): Plan => Plan::fromArray([
                'id' => 1,
                'plan_key' => 'trial',
                'price' => 0,
                'metadata' => json_encode(['features' => ['bookings' => 10]]),
            ]));

        $decision = $this->service->canChangeToPlan(12, $pro);

        $this->assertTrue((bool) ($decision['allowed'] ?? false));
        $this->assertFalse((bool) ($decision['is_downgrade'] ?? true));
    }
}
