<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Subscriptions;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Http\SubscriptionCheckoutEndpoint;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Services\BillingService;
use MYVH\Tests\Unit\UnitTestCase;

class SubscriptionCheckoutEndpointTest extends UnitTestCase {
    /** @var AccountRepository&MockInterface */
    private $account_repository;

    /** @var PlanRepository&MockInterface */
    private $plan_repository;

    /** @var BillingService&MockInterface */
    private $billing_service;

    private SubscriptionCheckoutEndpoint $endpoint;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            '__' => fn($value) => (string) $value,
            'sanitize_key' => fn($value) => strtolower((string) $value),
            'esc_url_raw' => fn($value) => trim((string) $value),
            'is_user_logged_in' => true,
            'current_user_can' => true,
        ]);

        /** @var AccountRepository&MockInterface $account_repository */
        $account_repository = $this->mock(AccountRepository::class);
        $this->account_repository = $account_repository;

        /** @var PlanRepository&MockInterface $plan_repository */
        $plan_repository = $this->mock(PlanRepository::class);
        $this->plan_repository = $plan_repository;

        /** @var BillingService&MockInterface $billing_service */
        $billing_service = $this->mock(BillingService::class);
        $this->billing_service = $billing_service;

        $this->endpoint = new SubscriptionCheckoutEndpoint(
            $this->account_repository,
            $this->plan_repository,
            $this->billing_service
        );
    }

    /** @test */
    public function handle_returns_bad_request_when_required_fields_are_missing(): void {
        /** @var \WP_REST_Request&MockInterface $request */
        $request = $this->mock(\WP_REST_Request::class);
        $request->shouldReceive('get_json_params')->once()->andReturnUsing(static fn(): array => [
            'plan_code' => 'pro',
        ]);

        $response = $this->endpoint->handle($request);

        $this->assertSame(400, $response->get_status());
        $this->assertFalse((bool) ($response->get_data()['success'] ?? true));
    }

    /** @test */
    public function handle_returns_not_found_when_account_does_not_exist(): void {
        /** @var \WP_REST_Request&MockInterface $request */
        $request = $this->mock(\WP_REST_Request::class);
        $request->shouldReceive('get_json_params')->once()->andReturnUsing(static fn(): array => [
            'account_id' => 99,
            'plan_code' => 'pro',
        ]);

        $this->account_repository->shouldReceive('get_by_id')->once()->with(99)->andReturn(null);
        $this->plan_repository->shouldReceive('getByCode')->never();
        $this->billing_service->shouldReceive('createStripeCheckoutUrl')->never();

        $response = $this->endpoint->handle($request);

        $this->assertSame(404, $response->get_status());
        $this->assertFalse((bool) ($response->get_data()['success'] ?? true));
    }

    /** @test */
    public function handle_returns_not_found_when_plan_does_not_exist(): void {
        /** @var \WP_REST_Request&MockInterface $request */
        $request = $this->mock(\WP_REST_Request::class);
        $request->shouldReceive('get_json_params')->once()->andReturnUsing(static fn(): array => [
            'account_id' => 10,
            'plan_code' => 'missing-plan',
        ]);

        $this->account_repository->shouldReceive('get_by_id')->once()->with(10)->andReturnUsing(static fn(): array => [
            'id' => 10,
        ]);
        $this->plan_repository->shouldReceive('getByCode')->once()->with('missing-plan')->andReturn(null);
        $this->billing_service->shouldReceive('createStripeCheckoutUrl')->never();

        $response = $this->endpoint->handle($request);

        $this->assertSame(404, $response->get_status());
        $this->assertFalse((bool) ($response->get_data()['success'] ?? true));
    }

    /** @test */
    public function handle_returns_server_error_when_checkout_url_generation_fails(): void {
        /** @var \WP_REST_Request&MockInterface $request */
        $request = $this->mock(\WP_REST_Request::class);
        $request->shouldReceive('get_json_params')->once()->andReturnUsing(static fn(): array => [
            'account_id' => 12,
            'plan_code' => 'pro',
        ]);

        $this->account_repository->shouldReceive('get_by_id')->once()->with(12)->andReturnUsing(static fn(): array => [
            'id' => 12,
        ]);
        $this->plan_repository->shouldReceive('getByCode')->once()->with('pro')->andReturnUsing(static fn(): Plan => Plan::fromArray([
            'id' => 3,
            'plan_key' => 'pro',
        ]));

        $this->billing_service->shouldReceive('createStripeCheckoutUrl')
            ->once()
            ->with(12, 'pro', '', '')
            ->andReturn(null);

        $response = $this->endpoint->handle($request);

        $this->assertSame(500, $response->get_status());
        $this->assertFalse((bool) ($response->get_data()['success'] ?? true));
    }

    /** @test */
    public function handle_returns_checkout_url_and_session_id_on_success(): void {
        /** @var \WP_REST_Request&MockInterface $request */
        $request = $this->mock(\WP_REST_Request::class);
        $request->shouldReceive('get_json_params')->once()->andReturnUsing(static fn(): array => [
            'account_id' => 14,
            'plan_code' => 'pro',
            'success_url' => 'https://example.test/success',
            'cancel_url' => 'https://example.test/cancel',
        ]);

        $this->account_repository->shouldReceive('get_by_id')->once()->with(14)->andReturnUsing(static fn(): array => [
            'id' => 14,
        ]);
        $this->plan_repository->shouldReceive('getByCode')->once()->with('pro')->andReturnUsing(static fn(): Plan => Plan::fromArray([
            'id' => 3,
            'plan_key' => 'pro',
        ]));

        $this->billing_service->shouldReceive('createStripeCheckoutUrl')
            ->once()
            ->with(14, 'pro', 'https://example.test/success', 'https://example.test/cancel')
            ->andReturnUsing(static fn(): array => [
                'success' => true,
                'checkout_url' => 'https://checkout.stripe.test/cs_abc',
                'session_id' => 'cs_abc',
            ]);

        $response = $this->endpoint->handle($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue((bool) ($response->get_data()['success'] ?? false));
        $this->assertSame('https://checkout.stripe.test/cs_abc', $response->get_data()['checkout_url'] ?? '');
        $this->assertSame('cs_abc', $response->get_data()['session_id'] ?? '');
    }
}
