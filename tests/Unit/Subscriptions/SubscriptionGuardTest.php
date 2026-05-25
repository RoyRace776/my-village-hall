<?php

namespace MYVH\Tests\Unit\Subscriptions;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Services\AccountService;
use MYVH\Subscriptions\Services\SettingsService;
use MYVH\Subscriptions\Services\SubscriptionGuard;
use MYVH\Subscriptions\Services\TrialService;
use MYVH\Subscriptions\Services\UsageService;
use MYVH\Tests\Unit\UnitTestCase;

class SubscriptionGuardTest extends UnitTestCase {
    /** @var TrialService&MockInterface */
    private $trial_service;

    /** @var SettingsService&MockInterface */
    private $settings_service;

    /** @var UsageService&MockInterface */
    private $usage_service;

    /** @var AccountService&MockInterface */
    private $account_service;

    private SubscriptionGuard $guard;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            '__' => fn($value) => (string) $value,
            'sanitize_key' => fn($value) => strtolower((string) $value),
            'get_current_blog_id' => 7,
            'current_time' => fn($type = 'mysql') => $type === 'timestamp' ? strtotime('2026-05-25 12:00:00') : '2026-05-25 12:00:00',
        ]);

        $this->trial_service = $this->mock(TrialService::class);
        $this->settings_service = $this->mock(SettingsService::class);
        $this->usage_service = $this->mock(UsageService::class);
        $this->account_service = $this->mock(AccountService::class);

        $this->guard = new SubscriptionGuard(
            $this->trial_service,
            $this->settings_service,
            $this->usage_service,
            $this->account_service
        );
    }

    /** @test */
    public function assert_can_create_booking_for_current_site_accepts_lowercase_account_id(): void {
        $this->account_service->shouldReceive('resolveAccountFromBlogId')
            ->once()
            ->with(7)
            ->andReturnUsing(static fn() => ['id' => 44]);

        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->once()
            ->with(44)
            ->andReturn(Subscription::fromArray([
                'id' => 101,
                'account_id' => 44,
                'status' => 'trialing',
                'trial_ends_at' => '2026-06-23 12:00:00',
            ]));

        $this->usage_service->shouldReceive('exceedsLimit')
            ->once()
            ->with(44)
            ->andReturn(false);

        $this->assertTrue($this->guard->assertCanCreateBookingForCurrentSite());
    }
}