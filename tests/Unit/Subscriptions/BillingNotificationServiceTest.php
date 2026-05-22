<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Subscriptions;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\BillingNotificationService;
use MYVH\Tests\Unit\UnitTestCase;

class BillingNotificationServiceTest extends UnitTestCase {
    /** @var SubscriptionRepository&MockInterface */
    private $subscription_repository;

    /** @var AccountRepository&MockInterface */
    private $account_repository;

    private BillingNotificationService $service;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            '__' => fn($value) => (string) $value,
            'sanitize_email' => fn($value) => trim((string) $value),
            'sanitize_key' => fn($value) => strtolower((string) $value),
            'is_email' => fn($value) => is_string($value) && str_contains($value, '@'),
            'get_bloginfo' => fn($key = '') => 'My Village Hall',
            'wp_json_encode' => fn($value) => json_encode($value),
            'wp_mail' => true,
        ]);

        /** @var SubscriptionRepository&MockInterface $subscription_repository */
        $subscription_repository = $this->mock(SubscriptionRepository::class);
        $this->subscription_repository = $subscription_repository;

        /** @var AccountRepository&MockInterface $account_repository */
        $account_repository = $this->mock(AccountRepository::class);
        $this->account_repository = $account_repository;

        $this->service = new BillingNotificationService($this->subscription_repository, $this->account_repository);
    }

    /** @test */
    public function sends_trial_ending_email_and_persists_notification_marker(): void {
        $today_marker = 'trial_ending_notified_' . gmdate('Ymd');

        $this->subscription_repository->shouldReceive('update_by_id')
            ->once()
            ->withArgs(static function (int $id, array $data) use ($today_marker): bool {
                return $id === 42
                    && isset($data['metadata'])
                    && is_string($data['metadata'])
                    && str_contains($data['metadata'], $today_marker);
            })
            ->andReturn(true);

        $result = $this->service->notifyTrialEndingSoon(
            ['id' => 10, 'contact_email' => 'billing@example.test'],
            Subscription::fromArray(['id' => 42, 'metadata' => '']),
            3
        );

        $this->assertTrue($result);
    }

    /** @test */
    public function skips_trial_ending_send_when_already_notified_today(): void {
        $today_marker = 'trial_ending_notified_' . gmdate('Ymd');
        $mail_calls = 0;

        Functions\when('wp_mail')->alias(function () use (&$mail_calls): bool {
            $mail_calls++;
            return true;
        });

        $this->subscription_repository->shouldReceive('update_by_id')->never();

        $result = $this->service->notifyTrialEndingSoon(
            ['id' => 10, 'contact_email' => 'billing@example.test'],
            Subscription::fromArray(['id' => 42, 'metadata' => json_encode([$today_marker => 1])]),
            2
        );

        $this->assertTrue($result);
        $this->assertSame(0, $mail_calls);
    }

    /** @test */
    public function sends_payment_failed_email_and_persists_event_marker(): void {
        $this->subscription_repository->shouldReceive('get_latest_by_stripe_subscription_id')
            ->once()
            ->with('sub_abc')
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray(['id' => 77, 'account_id' => 9, 'metadata' => '{}']));

        $this->account_repository->shouldReceive('get_by_id')
            ->once()
            ->with(9)
            ->andReturnUsing(static fn(): array => ['id' => 9, 'contact_email' => 'owner@example.test']);

        $this->subscription_repository->shouldReceive('update_by_id')
            ->once()
            ->withArgs(static function (int $id, array $data): bool {
                return $id === 77
                    && isset($data['metadata'])
                    && is_string($data['metadata'])
                    && str_contains($data['metadata'], 'payment_failed_event_evt_test_123');
            })
            ->andReturn(true);

        $result = $this->service->notifyPaymentFailedByStripeSubscriptionId('sub_abc', 'evt_test_123');

        $this->assertTrue($result);
    }

    /** @test */
    public function skips_payment_failed_email_when_event_already_notified(): void {
        $mail_calls = 0;

        Functions\when('wp_mail')->alias(function () use (&$mail_calls): bool {
            $mail_calls++;
            return true;
        });

        $this->subscription_repository->shouldReceive('get_latest_by_stripe_subscription_id')
            ->once()
            ->with('sub_dup')
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'id' => 81,
                'account_id' => 9,
                'metadata' => json_encode(['payment_failed_event_evt_dup_1' => 1]),
            ]));

        $this->account_repository->shouldReceive('get_by_id')
            ->once()
            ->with(9)
            ->andReturnUsing(static fn(): array => ['id' => 9, 'contact_email' => 'owner@example.test']);

        $this->subscription_repository->shouldReceive('update_by_id')->never();

        $result = $this->service->notifyPaymentFailedByStripeSubscriptionId('sub_dup', 'evt_dup_1');

        $this->assertTrue($result);
        $this->assertSame(0, $mail_calls);
    }

    /** @test */
    public function payment_failed_notification_returns_false_when_mail_throws(): void {
        Functions\when('wp_mail')->alias(static function (): bool {
            throw new \RuntimeException('mail transport failed');
        });

        $this->subscription_repository->shouldReceive('get_latest_by_stripe_subscription_id')
            ->once()
            ->with('sub_err')
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray(['id' => 88, 'account_id' => 12, 'metadata' => '{}']));

        $this->account_repository->shouldReceive('get_by_id')
            ->once()
            ->with(12)
            ->andReturnUsing(static fn(): array => ['id' => 12, 'contact_email' => 'owner@example.test']);

        $this->subscription_repository->shouldReceive('update_by_id')->never();

        $result = $this->service->notifyPaymentFailedByStripeSubscriptionId('sub_err', 'evt_err_1');

        $this->assertFalse($result);
    }
}
