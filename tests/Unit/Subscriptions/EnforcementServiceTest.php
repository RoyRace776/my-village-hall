<?php

namespace MYVH\Tests\Unit\Subscriptions;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\SettingsRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\EnforcementService;
use MYVH\Subscriptions\Services\TrialService;
use MYVH\Subscriptions\Services\UsageService;
use MYVH\Tests\Unit\UnitTestCase;

class EnforcementServiceTest extends UnitTestCase {
    /** @var SubscriptionRepository&MockInterface */
    private $subscription_repository;

    /** @var UsageService&MockInterface */
    private $usage_service;

    /** @var SettingsRepository&MockInterface */
    private $settings_repository;

    /** @var TrialService&MockInterface */
    private $trial_service;

    private EnforcementService $service;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            '__' => fn($value) => (string) $value,
            'sanitize_key' => fn($value) => strtolower((string) $value),
            'current_time' => fn($type = 'mysql') => $type === 'timestamp' ? 1716200000 : '2024-05-20 12:00:00',
        ]);

        /** @var SubscriptionRepository&MockInterface $subscription_repository */
        $subscription_repository = $this->mock(SubscriptionRepository::class);
        $this->subscription_repository = $subscription_repository;

        /** @var UsageService&MockInterface $usage_service */
        $usage_service = $this->mock(UsageService::class);
        $this->usage_service = $usage_service;

        /** @var SettingsRepository&MockInterface $settings_repository */
        $settings_repository = $this->mock(SettingsRepository::class);
        $this->settings_repository = $settings_repository;

        /** @var TrialService&MockInterface $trial_service */
        $trial_service = $this->mock(TrialService::class);
        $this->trial_service = $trial_service;

        $this->service = new EnforcementService($this->subscription_repository, $this->settings_repository, $this->usage_service, $this->trial_service);
    }

    /** @test */
    public function blocks_when_subscription_is_expired(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->twice()
            ->with(10)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray(['status' => 'expired']));

        $this->usage_service->shouldReceive('exceedsLimit')->never();

        $this->assertFalse($this->service->canCreateBooking(10));
        $this->assertSame('Subscription has expired', $this->service->getLastErrorMessage());
        $this->assertSame(['allowed' => false, 'reason' => 'expired'], $this->service->getBookingPermission(10));
    }

    /** @test */
    public function blocks_when_trial_has_expired(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->twice()
            ->with(10)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray(['status' => 'expired']));

        $this->assertFalse($this->service->canCreateBooking(10));
        $this->assertSame('Subscription has expired', $this->service->getLastErrorMessage());
        $this->assertSame(['allowed' => false, 'reason' => 'expired'], $this->service->getBookingPermission(10));
    }

    /** @test */
    public function blocks_when_subscription_is_cancelled(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->twice()
            ->with(10)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray(['status' => 'cancelled']));

        $this->assertFalse($this->service->canCreateBooking(10));
        $this->assertSame('Subscription has been cancelled', $this->service->getLastErrorMessage());
        $this->assertSame(['allowed' => false, 'reason' => 'cancelled'], $this->service->getBookingPermission(10));
    }

    /** @test */
    public function blocks_when_usage_limit_is_exceeded(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->once()
            ->with(10)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray(['status' => 'active']));

        $this->usage_service->shouldReceive('exceedsLimit')->once()->with(10)->andReturn(true);

        $this->assertFalse($this->service->canCreateBooking(10));
        $this->assertSame('Booking limit reached for this account', $this->service->getLastErrorMessage());
    }

    /** @test */
    public function allows_past_due_when_within_grace_period(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->twice()
            ->with(10)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'status' => 'past_due',
                'current_period_end' => '2024-05-19 12:00:00',
            ]));

        $this->settings_repository->shouldReceive('get_setting_value')->twice()->with('grace_period_days', 0)->andReturn(3);

        $this->usage_service->shouldReceive('exceedsLimit')->twice()->with(10)->andReturn(false);

        $this->assertTrue($this->service->canCreateBooking(10));
        $this->assertSame('', $this->service->getLastErrorMessage());
        $this->assertSame(['allowed' => true, 'reason' => ''], $this->service->getBookingPermission(10));
    }

    /** @test */
    public function blocks_past_due_when_beyond_grace_period(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->twice()
            ->with(10)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'status' => 'past_due',
                'current_period_end' => '2024-05-10 12:00:00',
            ]));

        $this->settings_repository->shouldReceive('get_setting_value')->twice()->with('grace_period_days', 0)->andReturn(3);

        $this->usage_service->shouldReceive('exceedsLimit')->never();

        $this->assertFalse($this->service->canCreateBooking(10));
        $this->assertSame('Subscription payment is overdue beyond grace period', $this->service->getLastErrorMessage());
        $this->assertSame(['allowed' => false, 'reason' => 'past_due'], $this->service->getBookingPermission(10));
    }

    /** @test */
    public function allows_when_active_and_within_limits(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->once()
            ->with(10)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray(['status' => 'active']));

        $this->usage_service->shouldReceive('exceedsLimit')->once()->with(10)->andReturn(false);

        $this->assertTrue($this->service->canCreateBooking(10));
        $this->assertSame('', $this->service->getLastErrorMessage());
    }

    /** @test */
    public function allows_when_trial_status_remains_trial_and_within_limits(): void {
        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->once()
            ->with(10)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray(['status' => 'trial']));

        $this->usage_service->shouldReceive('exceedsLimit')->once()->with(10)->andReturn(false);

        $this->assertTrue($this->service->canCreateBooking(10));
        $this->assertSame('', $this->service->getLastErrorMessage());
    }
}
