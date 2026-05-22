<?php

namespace MYVH\Tests\Unit\Subscriptions;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Http\StripeWebhookHandler;
use MYVH\Subscriptions\Repositories\ProcessedStripeEventRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SettingsRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\BillingNotificationService;
use MYVH\Subscriptions\Services\SubscriptionEventLogger;
use MYVH\Tests\Unit\UnitTestCase;
use Stripe\Event;

class StripeWebhookHandlerTest extends UnitTestCase {
    /** @var SettingsRepository&MockInterface */
    private $settings_repository;

    /** @var SubscriptionRepository&MockInterface */
    private $subscription_repository;

    /** @var ProcessedStripeEventRepository&MockInterface */
    private $processed_event_repository;

    /** @var BillingNotificationService&MockInterface */
    private $billing_notification_service;

    /** @var SubscriptionEventLogger&MockInterface */
    private $event_logger;

    /** @var PlanRepository&MockInterface */
    private $plan_repository;

    private StripeWebhookHandler $handler;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'sanitize_key' => fn($value) => strtolower((string) $value),
        ]);

        /** @var SettingsRepository&MockInterface $settings_repository */
        $settings_repository = $this->mock(SettingsRepository::class);
        $this->settings_repository = $settings_repository;

        /** @var SubscriptionRepository&MockInterface $subscription_repository */
        $subscription_repository = $this->mock(SubscriptionRepository::class);
        $this->subscription_repository = $subscription_repository;

        /** @var ProcessedStripeEventRepository&MockInterface $processed_event_repository */
        $processed_event_repository = $this->mock(ProcessedStripeEventRepository::class);
        $this->processed_event_repository = $processed_event_repository;

        /** @var BillingNotificationService&MockInterface $billing_notification_service */
        $billing_notification_service = $this->mock(BillingNotificationService::class);
        $this->billing_notification_service = $billing_notification_service;

        /** @var SubscriptionEventLogger&MockInterface $event_logger */
        $event_logger = $this->mock(SubscriptionEventLogger::class);
        $this->event_logger = $event_logger;

        /** @var PlanRepository&MockInterface $plan_repository */
        $plan_repository = $this->mock(PlanRepository::class);
        $this->plan_repository = $plan_repository;

        $this->handler = new StripeWebhookHandler(
            $this->settings_repository,
            $this->subscription_repository,
            $this->processed_event_repository,
            $this->billing_notification_service,
            $this->event_logger,
            new \Psr\Log\NullLogger(),
            $this->plan_repository
        );
    }

    /** @test */
    public function invoice_paid_maps_to_active_status_update(): void {
        $this->subscription_repository->shouldReceive('get_latest_by_stripe_subscription_id')
            ->once()
            ->with('sub_paid_123')
            ->andReturn(null);

        $this->subscription_repository->shouldReceive('update_status_by_stripe_subscription_id')
            ->once()
            ->with('sub_paid_123', 'active')
            ->andReturn(true);

        $this->event_logger->shouldReceive('log')->never();
        $this->subscription_repository->shouldReceive('update_period_by_stripe_subscription_id')->never();

        $event = $this->make_event('invoice.paid', [
            'subscription' => 'sub_paid_123',
        ]);
        $this->assertSame('invoice.paid', (string) $event->type);

        $this->invoke_dispatch_event($event);
    }

    /** @test */
    public function invoice_payment_failed_maps_to_past_due_status_update(): void {
        $this->subscription_repository->shouldReceive('get_latest_by_stripe_subscription_id')
            ->once()
            ->with('sub_fail_456')
            ->andReturn(null);

        $this->subscription_repository->shouldReceive('update_status_by_stripe_subscription_id')
            ->once()
            ->with('sub_fail_456', 'past_due')
            ->andReturn(true);

        $this->billing_notification_service->shouldReceive('notifyPaymentFailedByStripeSubscriptionId')
            ->once()
            ->with('sub_fail_456', 'evt_test_123')
            ->andReturn(true);

        $this->event_logger->shouldReceive('log')->never();
        $this->subscription_repository->shouldReceive('update_period_by_stripe_subscription_id')->never();

        $event = $this->make_event('invoice.payment_failed', [
            'subscription' => 'sub_fail_456',
        ]);
        $this->assertSame('invoice.payment_failed', (string) $event->type);

        $this->invoke_dispatch_event($event);
    }

    /** @test */
    public function customer_subscription_deleted_maps_to_cancelled_status_update(): void {
        $this->subscription_repository->shouldReceive('get_latest_by_stripe_subscription_id')
            ->once()
            ->with('sub_deleted_789')
            ->andReturn(null);

        $this->subscription_repository->shouldReceive('update_status_by_stripe_subscription_id')
            ->once()
            ->with('sub_deleted_789', 'cancelled')
            ->andReturn(true);

        $this->event_logger->shouldReceive('log')->never();
        $this->subscription_repository->shouldReceive('update_period_by_stripe_subscription_id')->never();

        $event = $this->make_event('customer.subscription.deleted', [
            'id' => 'sub_deleted_789',
        ]);
        $this->assertSame('customer.subscription.deleted', (string) $event->type);

        $this->invoke_dispatch_event($event);
    }

    /** @test */
    public function customer_subscription_updated_maps_to_period_end_update(): void {
        $this->subscription_repository->shouldReceive('update_status_by_stripe_subscription_id')->never();

        $this->subscription_repository->shouldReceive('update_period_by_stripe_subscription_id')
            ->once()
            ->with('sub_updated_555', 1716303600, 1713711600)
            ->andReturn(true);

        $this->plan_repository->shouldReceive('getByStripePriceId')
            ->once()
            ->with('price_pro_123')
            ->andReturnUsing(static fn(): Plan => Plan::fromArray([
                'id' => 2,
                'plan_key' => 'pro',
                'code' => 'pro',
            ]));

        $this->subscription_repository->shouldReceive('update_plan_code_by_stripe_subscription_id')
            ->once()
            ->with('sub_updated_555', 'pro')
            ->andReturn(true);

        $event = $this->make_event('customer.subscription.updated', [
            'id' => 'sub_updated_555',
            'current_period_start' => 1713711600,
            'current_period_end' => 1716303600,
            'items' => [
                'data' => [
                    [
                        'price' => ['id' => 'price_pro_123'],
                    ],
                ],
            ],
        ]);
        $this->assertSame('customer.subscription.updated', (string) $event->type);

        $this->invoke_dispatch_event($event);
    }

    /** @test */
    public function checkout_session_completed_sets_active_and_updates_plan_code_from_metadata(): void {
        $this->subscription_repository->shouldReceive('get_latest_by_stripe_subscription_id')
            ->once()
            ->with('sub_checkout_123')
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'account_id' => 88,
                'status' => 'trialing',
            ]));

        $this->subscription_repository->shouldReceive('update_status_by_stripe_subscription_id')
            ->once()
            ->with('sub_checkout_123', 'active')
            ->andReturn(true);

        $this->subscription_repository->shouldReceive('update_plan_code_by_stripe_subscription_id')
            ->once()
            ->with('sub_checkout_123', 'pro')
            ->andReturn(true);

        $this->event_logger->shouldReceive('log')
            ->once()
            ->withArgs(static function(
                int $account_id,
                string $event,
                ?string $previous_status,
                string $new_status,
                string $source,
                array $context
            ): bool {
                return $account_id === 88
                    && $event === 'stripe_checkout_completed'
                    && $previous_status === 'trialing'
                    && $new_status === 'active'
                    && $source === 'stripe_webhook'
                    && ($context['plan_code'] ?? '') === 'pro';
            })
            ->andReturn(true);

        $event = $this->make_event('checkout.session.completed', [
            'subscription' => 'sub_checkout_123',
            'metadata' => [
                'plan_code' => 'pro',
            ],
        ]);

        $this->invoke_dispatch_event($event);
    }

    private function invoke_dispatch_event(Event $event): void {
        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('dispatch_event');
        $method->setAccessible(true);
        $method->invoke($this->handler, $event);
    }

    private function make_event(string $type, array $object): Event {
        return Event::constructFrom([
            'id' => 'evt_test_123',
            'object' => 'event',
            'type' => $type,
            'data' => [
                'object' => $object,
            ],
        ]);
    }
}
