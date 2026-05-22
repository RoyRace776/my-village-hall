<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Subscriptions;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Http\StripeWebhookHandler;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\BillingService;
use MYVH\Subscriptions\Services\ManualInvoiceService;
use MYVH\Subscriptions\Services\StripeService;
use MYVH\Tests\Unit\UnitTestCase;

class BillingServiceTest extends UnitTestCase {
    /** @var StripeService&MockInterface */
    private $stripe_service;

    /** @var StripeWebhookHandler&MockInterface */
    private $stripe_webhook_handler;

    /** @var ManualInvoiceService&MockInterface */
    private $manual_invoice_service;

    /** @var AccountRepository&MockInterface */
    private $account_repository;

    /** @var PlanRepository&MockInterface */
    private $plan_repository;

    /** @var SubscriptionRepository&MockInterface */
    private $subscription_repository;

    private BillingService $service;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'sanitize_key' => fn($value) => strtolower((string) $value),
            'sanitize_email' => fn($value) => trim((string) $value),
            'sanitize_text_field' => fn($value) => trim((string) $value),
            'esc_url_raw' => fn($value) => trim((string) $value),
            'current_time' => fn($type = 'mysql') => $type === 'timestamp' ? strtotime('2026-05-21 10:00:00') : '2026-05-21 10:00:00',
            'admin_url' => fn($path = '') => 'https://example.test/wp-admin/' . ltrim((string) $path, '/'),
            'add_query_arg' => static function(array $args, string $url): string {
                $query = http_build_query($args);
                return $url . (str_contains($url, '?') ? '&' : '?') . $query;
            },
        ]);

        /** @var StripeService&MockInterface $stripe_service */
        $stripe_service = $this->mock(StripeService::class);
        $this->stripe_service = $stripe_service;

        /** @var StripeWebhookHandler&MockInterface $stripe_webhook_handler */
        $stripe_webhook_handler = $this->mock(StripeWebhookHandler::class);
        $this->stripe_webhook_handler = $stripe_webhook_handler;

        /** @var ManualInvoiceService&MockInterface $manual_invoice_service */
        $manual_invoice_service = $this->mock(ManualInvoiceService::class);
        $this->manual_invoice_service = $manual_invoice_service;

        /** @var AccountRepository&MockInterface $account_repository */
        $account_repository = $this->mock(AccountRepository::class);
        $this->account_repository = $account_repository;

        /** @var PlanRepository&MockInterface $plan_repository */
        $plan_repository = $this->mock(PlanRepository::class);
        $this->plan_repository = $plan_repository;

        /** @var SubscriptionRepository&MockInterface $subscription_repository */
        $subscription_repository = $this->mock(SubscriptionRepository::class);
        $this->subscription_repository = $subscription_repository;

        $this->service = new BillingService(
            $this->stripe_service,
            $this->stripe_webhook_handler,
            $this->manual_invoice_service,
            $this->account_repository,
            $this->plan_repository,
            $this->subscription_repository
        );
    }

    /** @test */
    public function create_checkout_session_reuses_existing_customer_and_subscription_ids(): void {
        $this->account_repository->shouldReceive('get_by_id')
            ->once()
            ->with(10)
            ->andReturnUsing(static fn(): array => [
                'id' => 10,
                'account_name' => 'Village Hall',
                'contact_email' => 'billing@example.test',
                'blog_id' => 21,
            ]);

        $this->subscription_repository->shouldReceive('get_active_by_account_id')
            ->once()
            ->with(10)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'stripe_customer_id' => 'cus_existing_123',
            ]));

        $this->plan_repository->shouldReceive('getStripePriceIdMonthlyByCode')
            ->once()
            ->with('pro')
            ->andReturn('price_pro_123');

        $this->subscription_repository->shouldReceive('get_active_by_stripe_customer_id')
            ->once()
            ->with('cus_existing_123')
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'stripe_subscription_id' => 'sub_existing_456',
            ]));

        $this->stripe_service->shouldReceive('createCustomer')->never();
        $this->stripe_service->shouldReceive('createSubscription')->never();
        $this->subscription_repository->shouldReceive('update_latest_stripe_customer_id_for_account')->never();
        $this->subscription_repository->shouldReceive('update_latest_stripe_subscription_id_for_customer')->never();

        $result = $this->service->createCheckoutSession(10, 'pro', 'stripe');

        $this->assertIsArray($result);
        $this->assertTrue((bool) ($result['success'] ?? false));
        $this->assertSame('cus_existing_123', $result['customer_id']);
        $this->assertSame('sub_existing_456', $result['subscription_id']);
    }

    /** @test */
    public function create_checkout_session_creates_stripe_customer_and_subscription_when_missing(): void {
        $this->account_repository->shouldReceive('get_by_id')
            ->once()
            ->with(11)
            ->andReturnUsing(static fn(): array => [
                'id' => 11,
                'account_name' => 'Village Hall B',
                'contact_email' => 'billing2@example.test',
                'blog_id' => 31,
            ]);

        $this->subscription_repository->shouldReceive('get_active_by_account_id')
            ->once()
            ->with(11)
            ->andReturn(null);

        $this->stripe_service->shouldReceive('createCustomer')
            ->once()
            ->with('billing2@example.test', 'Village Hall B', [
                'myvh_account_id' => '11',
                'myvh_blog_id' => '31',
            ])
            ->andReturnUsing(static fn(): array => ['id' => 'cus_new_123']);

        $this->subscription_repository->shouldReceive('update_latest_stripe_customer_id_for_account')
            ->once()
            ->with(11, 'cus_new_123')
            ->andReturn(true);

        $this->plan_repository->shouldReceive('getStripePriceIdMonthlyByCode')
            ->once()
            ->with('pro')
            ->andReturn('price_pro_777');

        $this->subscription_repository->shouldReceive('get_active_by_stripe_customer_id')
            ->once()
            ->with('cus_new_123')
            ->andReturn(null);

        $this->stripe_service->shouldReceive('createSubscription')
            ->once()
            ->with('cus_new_123', 'price_pro_777')
            ->andReturnUsing(static fn(): array => ['id' => 'sub_new_888']);

        $this->subscription_repository->shouldReceive('update_latest_stripe_subscription_id_for_customer')
            ->once()
            ->with('cus_new_123', 'sub_new_888')
            ->andReturn(true);

        $result = $this->service->createCheckoutSession(11, 'pro', 'stripe');

        $this->assertIsArray($result);
        $this->assertTrue((bool) ($result['success'] ?? false));
        $this->assertSame('cus_new_123', $result['customer_id']);
        $this->assertSame('sub_new_888', $result['subscription_id']);
    }

    /** @test */
    public function cancel_provider_subscription_cancels_on_stripe_and_local_subscription(): void {
        $this->stripe_service->shouldReceive('cancelSubscription')
            ->once()
            ->with('sub_123')
            ->andReturn(true);

        $this->subscription_repository->shouldReceive('cancel_by_stripe_subscription_id')
            ->once()
            ->with('sub_123')
            ->andReturn(true);

        $this->assertTrue($this->service->cancelProviderSubscription('sub_123', 'stripe'));
    }

    /** @test */
    public function create_stripe_checkout_url_returns_checkout_url_and_session_id(): void {
        $this->account_repository->shouldReceive('get_by_id')
            ->once()
            ->with(44)
            ->andReturnUsing(static fn(): array => [
                'id' => 44,
                'account_name' => 'Village Hall C',
                'contact_email' => 'billing3@example.test',
                'blog_id' => 55,
            ]);

        $this->subscription_repository->shouldReceive('get_active_by_account_id')
            ->once()
            ->with(44)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'stripe_customer_id' => 'cus_existing_checkout_1',
            ]));

        $this->plan_repository->shouldReceive('getStripePriceIdMonthlyByCode')
            ->once()
            ->with('pro')
            ->andReturn('price_checkout_123');

        $this->stripe_service->shouldReceive('createCheckoutSession')
            ->once()
            ->with(
                'cus_existing_checkout_1',
                'price_checkout_123',
                'https://example.test/success',
                'https://example.test/cancel',
                [
                    'plan_code' => 'pro',
                    'myvh_account_id' => '44',
                ]
            )
            ->andReturnUsing(static fn(): array => [
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.test/cs_test_123',
            ]);

        $result = $this->service->createStripeCheckoutUrl(
            44,
            'pro',
            'https://example.test/success',
            'https://example.test/cancel'
        );

        $this->assertIsArray($result);
        $this->assertTrue((bool) ($result['success'] ?? false));
        $this->assertSame('https://checkout.stripe.test/cs_test_123', $result['checkout_url'] ?? '');
        $this->assertSame('cs_test_123', $result['session_id'] ?? '');
    }

    /** @test */
    public function request_plan_change_blocks_downgrade_to_trial_plan(): void {
        $this->plan_repository->shouldReceive('getByCode')
            ->once()
            ->with('trial')
            ->andReturnUsing(static fn(): \MYVH\Subscriptions\Entities\Plan => \MYVH\Subscriptions\Entities\Plan::fromArray([
                'id' => 1,
                'plan_key' => 'trial',
                'price' => 0,
                'metadata' => json_encode(['features' => ['bookings' => 10]]),
            ]));

        $this->subscription_repository->shouldReceive('get_active_by_account_id')
            ->once()
            ->with(52)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'id' => 600,
                'plan_code' => 'pro',
                'status' => 'active',
            ]));

        $this->plan_repository->shouldReceive('getByCode')
            ->twice()
            ->with('pro')
            ->andReturnUsing(static fn(): \MYVH\Subscriptions\Entities\Plan => \MYVH\Subscriptions\Entities\Plan::fromArray([
                'id' => 3,
                'plan_key' => 'pro',
                'price' => 29,
                'metadata' => json_encode(['features' => ['bookings' => null]]),
            ]));

        $this->subscription_repository->shouldReceive('update_by_id')->never();
        $this->subscription_repository->shouldReceive('update_latest_plan_for_account')->never();

        $result = $this->service->requestPlanChange(52, 'trial', 'stripe');

        $this->assertNull($result);
    }

    /** @test */
    public function request_plan_change_schedules_allowed_downgrade_for_period_end(): void {
        $this->plan_repository->shouldReceive('getByCode')
            ->once()
            ->with('basic')
            ->andReturnUsing(static fn(): \MYVH\Subscriptions\Entities\Plan => \MYVH\Subscriptions\Entities\Plan::fromArray([
                'id' => 2,
                'plan_key' => 'basic',
                'price' => 9,
                'metadata' => json_encode(['features' => ['bookings' => 30]]),
            ]));

        $this->subscription_repository->shouldReceive('get_active_by_account_id')
            ->once()
            ->with(77)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'id' => 321,
                'plan_code' => 'pro',
                'status' => 'active',
                'current_period_end' => '2026-06-30 00:00:00',
            ]));

        $this->plan_repository->shouldReceive('getByCode')
            ->twice()
            ->with('pro')
            ->andReturnUsing(static fn(): \MYVH\Subscriptions\Entities\Plan => \MYVH\Subscriptions\Entities\Plan::fromArray([
                'id' => 3,
                'plan_key' => 'pro',
                'price' => 29,
                'metadata' => json_encode(['features' => ['bookings' => null]]),
            ]));

        $this->subscription_repository->shouldReceive('update_by_id')
            ->once()
            ->withArgs(function (int $subscriptionId, array $data): bool {
                if ($subscriptionId !== 321) {
                    return false;
                }

                $metadata = json_decode((string) ($data['metadata'] ?? ''), true);
                if (!is_array($metadata)) {
                    return false;
                }

                $scheduled = $metadata['scheduled_plan_change'] ?? [];

                return ($scheduled['plan_code'] ?? '') === 'basic'
                    && ($scheduled['plan_id'] ?? 0) === 2
                    && ($scheduled['effective_at'] ?? '') === '2026-06-30 00:00:00';
            })
            ->andReturn(true);

        $this->subscription_repository->shouldReceive('update_latest_plan_for_account')->never();

        $result = $this->service->requestPlanChange(77, 'basic', 'stripe');

        $this->assertIsArray($result);
        $this->assertTrue((bool) ($result['success'] ?? false));
        $this->assertTrue((bool) ($result['scheduled'] ?? false));
        $this->assertSame('basic', $result['plan_code'] ?? '');
    }

    /** @test */
    public function create_checkout_session_returns_null_when_stripe_runtime_exception_occurs(): void {
        $this->account_repository->shouldReceive('get_by_id')
            ->once()
            ->with(90)
            ->andReturnUsing(static fn(): array => [
                'id' => 90,
                'account_name' => 'Village Hall X',
                'contact_email' => 'billing@example.test',
                'blog_id' => 1,
            ]);

        $this->subscription_repository->shouldReceive('get_active_by_account_id')
            ->once()
            ->with(90)
            ->andReturn(null);

        $this->stripe_service->shouldReceive('createCustomer')
            ->once()
            ->andThrow(new \RuntimeException('Stripe secret key is not configured'));

        $this->subscription_repository->shouldReceive('update_latest_stripe_customer_id_for_account')->never();

        $result = $this->service->createCheckoutSession(90, 'pro', 'stripe');

        $this->assertNull($result);
    }
}
