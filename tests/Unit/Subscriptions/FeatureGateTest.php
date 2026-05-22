<?php

namespace MYVH\Tests\Unit\Subscriptions;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\FeatureGate;
use MYVH\Subscriptions\Services\TrialService;
use MYVH\Tests\Unit\UnitTestCase;

class FeatureGateTest extends UnitTestCase {
    /** @var SubscriptionRepository&MockInterface */
    private $subscription_repository;

    /** @var PlanRepository&MockInterface */
    private $plan_repository;

    /** @var TrialService&MockInterface */
    private $trial_service;

    private FeatureGate $feature_gate;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'sanitize_key' => fn($value) => strtolower((string) $value),
            'current_time' => fn($type = 'mysql') => $type === 'timestamp' ? 1716200000 : '2024-05-20 12:00:00',
        ]);

        /** @var SubscriptionRepository&MockInterface $subscription_repository */
        $subscription_repository = $this->mock(SubscriptionRepository::class);
        $this->subscription_repository = $subscription_repository;

        /** @var PlanRepository&MockInterface $plan_repository */
        $plan_repository = $this->mock(PlanRepository::class);
        $this->plan_repository = $plan_repository;

        /** @var TrialService&MockInterface $trial_service */
        $trial_service = $this->mock(TrialService::class);
        $this->trial_service = $trial_service;

        $this->feature_gate = new FeatureGate(
            $this->subscription_repository,
            $this->plan_repository,
            $this->trial_service
        );
    }

    /** @test */
    public function allows_returns_false_when_subscription_is_expired(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->once()
            ->with(12)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray(['id' => 100, 'account_id' => 12, 'status' => 'expired', 'plan_code' => 'basic']));

        $this->plan_repository->shouldReceive('getByCode')->never();

        $this->assertFalse($this->feature_gate->allows(12, 'invoicing'));
    }

    /** @test */
    public function allows_returns_false_when_subscription_is_cancelled(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->once()
            ->with(12)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray(['id' => 100, 'account_id' => 12, 'status' => 'cancelled', 'plan_code' => 'basic']));

        $this->plan_repository->shouldReceive('getByCode')->never();

        $this->assertFalse($this->feature_gate->allows(12, 'invoicing'));
    }

    /** @test */
    public function allows_returns_true_for_active_subscription_when_feature_is_enabled(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->once()
            ->with(12)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'id' => 101,
                'account_id' => 12,
                'status' => 'active',
                'plan_code' => 'standard',
            ]));

        $this->plan_repository->shouldReceive('getByCode')
            ->once()
            ->with('standard')
            ->andReturnUsing(static fn(): Plan => Plan::fromArray([
                'id' => 2,
                'plan_key' => 'standard',
                'metadata' => '{"features":{"bookings":150,"deposits":true,"invoicing":true,"reporting":true}}',
            ]));

        $this->assertTrue($this->feature_gate->allows(12, 'invoicing'));
    }

    /** @test */
    public function allows_returns_false_for_active_subscription_when_feature_is_disabled(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->once()
            ->with(12)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'id' => 101,
                'account_id' => 12,
                'status' => 'active',
                'plan_code' => 'basic',
            ]));

        $this->plan_repository->shouldReceive('getByCode')
            ->once()
            ->with('basic')
            ->andReturnUsing(static fn(): Plan => Plan::fromArray([
                'id' => 1,
                'plan_key' => 'basic',
                'metadata' => '{"features":{"bookings":30,"deposits":false,"invoicing":true,"reporting":false}}',
            ]));

        $this->assertFalse($this->feature_gate->allows(12, 'deposits'));
    }

    /** @test */
    public function allows_returns_false_when_trial_has_expired_to_expired_status(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->once()
            ->with(12)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'id' => 103,
                'account_id' => 12,
                'status' => 'expired',
                'plan_code' => 'standard',
            ]));

        $this->plan_repository->shouldReceive('getByCode')->never();

        $this->assertFalse($this->feature_gate->allows(12, 'invoicing'));
    }

    /** @test */
    public function allows_supports_unlimited_null_feature_value(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->once()
            ->with(12)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'id' => 104,
                'account_id' => 12,
                'status' => 'active',
                'plan_code' => 'pro',
            ]));

        $this->plan_repository->shouldReceive('getByCode')
            ->once()
            ->with('pro')
            ->andReturnUsing(static fn(): Plan => Plan::fromArray([
                'id' => 3,
                'plan_key' => 'pro',
                'metadata' => '{"features":{"bookings":null,"deposits":true,"invoicing":true,"reporting":true}}',
            ]));

        $this->assertTrue($this->feature_gate->allows(12, 'bookings'));
    }

    /** @test */
    public function allows_permits_past_due_subscription_when_feature_is_enabled(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->once()
            ->with(12)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'id' => 105,
                'account_id' => 12,
                'status' => 'past_due',
                'plan_code' => 'standard',
            ]));

        $this->plan_repository->shouldReceive('getByCode')
            ->once()
            ->with('standard')
            ->andReturnUsing(static fn(): Plan => Plan::fromArray([
                'id' => 2,
                'plan_key' => 'standard',
                'metadata' => '{"features":{"invoicing":true}}',
            ]));

        $this->assertTrue($this->feature_gate->allows(12, 'invoicing'));
    }
}
