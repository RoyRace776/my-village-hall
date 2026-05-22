<?php

namespace MYVH\Tests\Unit\Subscriptions;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\SettingsRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\TrialService;
use MYVH\Tests\Unit\UnitTestCase;

class TrialServiceTest extends UnitTestCase {
    /** @var SubscriptionRepository&MockInterface */
    private $subscription_repository;

    /** @var SettingsRepository&MockInterface */
    private $settings_repository;

    private TrialService $service;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'current_time' => fn($type = 'mysql') => $type === 'timestamp' ? 1716200000 : '2024-05-20 12:00:00',
            'sanitize_key' => fn($value) => strtolower((string) $value),
        ]);

        /** @var SubscriptionRepository&MockInterface $subscription_repository */
        $subscription_repository = $this->mock(SubscriptionRepository::class);
        $this->subscription_repository = $subscription_repository;

        /** @var SettingsRepository&MockInterface $settings_repository */
        $settings_repository = $this->mock(SettingsRepository::class);
        $this->settings_repository = $settings_repository;

        $this->service = new TrialService($this->subscription_repository, $this->settings_repository);
    }

    /** @test */
    public function check_and_expire_trial_updates_expired_trial_status(): void {
        $this->subscription_repository->shouldReceive('get_active_by_account_id')
            ->once()
            ->with(15)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'id' => 81,
                'status' => 'trial',
                'trial_ends_at' => '2024-05-01 12:00:00',
            ]));

        $this->subscription_repository->shouldReceive('update_by_id')
            ->once()
            ->with(81, ['status' => 'expired'])
            ->andReturn(true);

        $subscription = $this->service->checkAndExpireTrial(15);

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertTrue($subscription->isExpired());
    }

    /** @test */
    public function check_and_expire_trial_leaves_active_trial_unchanged(): void {
        $this->subscription_repository->shouldReceive('get_active_by_account_id')
            ->once()
            ->with(15)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'id' => 82,
                'status' => 'trial',
                'trial_ends_at' => '2024-05-25 12:00:00',
            ]));

        $this->subscription_repository->shouldReceive('update_by_id')->never();

        $subscription = $this->service->checkAndExpireTrial(15);

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertTrue($subscription->isTrial());
    }
}