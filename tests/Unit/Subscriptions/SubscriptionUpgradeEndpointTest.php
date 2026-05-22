<?php

namespace MYVH\Tests\Unit\Subscriptions;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Http\SubscriptionUpgradeEndpoint;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\BillingService;
use MYVH\Subscriptions\Services\PlanChangePolicyService;
use MYVH\Tests\Unit\UnitTestCase;

class SubscriptionUpgradeEndpointTest extends UnitTestCase {
    /** @var AccountRepository&MockInterface */
    private $account_repository;

    /** @var SubscriptionRepository&MockInterface */
    private $subscription_repository;

    /** @var PlanRepository&MockInterface */
    private $plan_repository;

    /** @var BillingService&MockInterface */
    private $billing_service;

    private SubscriptionUpgradeEndpoint $endpoint;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            '__' => fn($value) => (string) $value,
            'sanitize_key' => fn($value) => strtolower((string) $value),
            'is_user_logged_in' => true,
            'current_user_can' => true,
            'is_wp_error' => static fn($value): bool => $value instanceof \WP_Error,
        ]);

        /** @var AccountRepository&MockInterface $account_repository */
        $account_repository = $this->mock(AccountRepository::class);
        $this->account_repository = $account_repository;

        /** @var SubscriptionRepository&MockInterface $subscription_repository */
        $subscription_repository = $this->mock(SubscriptionRepository::class);
        $this->subscription_repository = $subscription_repository;

        /** @var PlanRepository&MockInterface $plan_repository */
        $plan_repository = $this->mock(PlanRepository::class);
        $this->plan_repository = $plan_repository;

        /** @var BillingService&MockInterface $billing_service */
        $billing_service = $this->mock(BillingService::class);
        $this->billing_service = $billing_service;

        $this->endpoint = new SubscriptionUpgradeEndpoint(
            $this->account_repository,
            $this->subscription_repository,
            $this->plan_repository,
            $this->billing_service
        );
    }

    /** @test */
    public function handle_returns_bad_request_when_required_fields_are_missing(): void {
        /** @var \WP_REST_Request&MockInterface $request */
        $request = $this->mock(\WP_REST_Request::class);
        $request->shouldReceive('get_json_params')->once()->andReturnUsing(static fn(): array => ['plan_code' => 'basic']);

        $response = $this->endpoint->handle($request);

        $this->assertSame(400, $response->get_status());
        $this->assertFalse((bool) $response->get_data()['success']);
    }

    /** @test */
    public function handle_upgrades_with_stripe_when_payment_method_is_stripe(): void {
        /** @var \WP_REST_Request&MockInterface $request */
        $request = $this->mock(\WP_REST_Request::class);
        $request->shouldReceive('get_json_params')->once()->andReturnUsing(static fn(): array => [
            'account_id' => 10,
            'plan_code' => 'pro',
            'payment_method' => 'stripe',
        ]);

        $this->account_repository->shouldReceive('get_by_id')->once()->with(10)->andReturnUsing(static fn(): array => [
            'id' => 10,
            'account_name' => 'Village Hall',
            'contact_email' => 'billing@example.test',
        ]);

        $this->plan_repository->shouldReceive('getByCode')->once()->with('pro')->andReturnUsing(static fn(): Plan => Plan::fromArray([
            'id' => 3,
            'plan_key' => 'pro',
        ]));

        $this->subscription_repository->shouldReceive('update_latest_plan_for_account')->never();
        $this->billing_service->shouldReceive('requestPlanChange')
            ->once()
            ->with(10, 'pro', 'stripe')
            ->andReturnUsing(static fn(): array => [
                'success' => true,
                'customer_id' => 'cus_123',
                'subscription_id' => 'sub_123',
            ]);

        $response = $this->endpoint->handle($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue((bool) $response->get_data()['success']);
        $this->assertSame('stripe', $response->get_data()['payment_method']);
        $this->assertSame('sub_123', $response->get_data()['billing']['subscription_id']);
    }

    /** @test */
    public function handle_sets_invoice_billing_when_payment_method_is_invoice(): void {
        /** @var \WP_REST_Request&MockInterface $request */
        $request = $this->mock(\WP_REST_Request::class);
        $request->shouldReceive('get_json_params')->once()->andReturnUsing(static fn(): array => [
            'account_id' => 11,
            'plan_code' => 'basic',
            'payment_method' => 'invoice',
        ]);

        $this->account_repository->shouldReceive('get_by_id')->once()->with(11)->andReturnUsing(static fn(): array => [
            'id' => 11,
        ]);

        $this->plan_repository->shouldReceive('getByCode')->once()->with('basic')->andReturnUsing(static fn(): Plan => Plan::fromArray([
            'id' => 1,
            'plan_key' => 'basic',
        ]));

        $this->subscription_repository->shouldReceive('update_latest_plan_for_account')->never();
        $this->billing_service->shouldReceive('requestPlanChange')
            ->once()
            ->with(11, 'basic', 'invoice')
            ->andReturnUsing(static fn(): array => [
                'success' => true,
                'invoice_id' => 7011,
            ]);

        $response = $this->endpoint->handle($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue((bool) $response->get_data()['success']);
        $this->assertSame('invoice', $response->get_data()['payment_method']);
        $this->assertSame(7011, $response->get_data()['billing']['invoice_id']);
    }

    /** @test */
    public function handle_returns_not_found_when_plan_code_does_not_exist(): void {
        /** @var \WP_REST_Request&MockInterface $request */
        $request = $this->mock(\WP_REST_Request::class);
        $request->shouldReceive('get_json_params')->once()->andReturnUsing(static fn(): array => [
            'account_id' => 12,
            'plan_code' => 'missing-plan',
            'payment_method' => 'stripe',
        ]);

        $this->account_repository->shouldReceive('get_by_id')->once()->with(12)->andReturnUsing(static fn(): array => [
            'id' => 12,
        ]);

        $this->plan_repository->shouldReceive('getByCode')->once()->with('missing-plan')->andReturn(null);

        $this->subscription_repository->shouldReceive('update_latest_plan_for_account')->never();
        $this->billing_service->shouldReceive('requestPlanChange')->never();

        $response = $this->endpoint->handle($request);

        $this->assertSame(404, $response->get_status());
        $this->assertFalse((bool) $response->get_data()['success']);
    }

    /** @test */
    public function handle_returns_bad_request_for_unsupported_payment_method(): void {
        /** @var \WP_REST_Request&MockInterface $request */
        $request = $this->mock(\WP_REST_Request::class);
        $request->shouldReceive('get_json_params')->once()->andReturnUsing(static fn(): array => [
            'account_id' => 13,
            'plan_code' => 'basic',
            'payment_method' => 'bank_transfer',
        ]);

        $this->account_repository->shouldReceive('get_by_id')->once()->with(13)->andReturnUsing(static fn(): array => [
            'id' => 13,
        ]);

        $this->plan_repository->shouldReceive('getByCode')->once()->with('basic')->andReturnUsing(static fn(): Plan => Plan::fromArray([
            'id' => 1,
            'plan_key' => 'basic',
        ]));

        $this->subscription_repository->shouldReceive('update_latest_plan_for_account')->never();
        $this->billing_service->shouldReceive('requestPlanChange')->never();

        $response = $this->endpoint->handle($request);

        $this->assertSame(400, $response->get_status());
        $this->assertFalse((bool) $response->get_data()['success']);
    }

    /** @test */
    public function handle_returns_unprocessable_when_policy_blocks_plan_change(): void {
        /** @var PlanChangePolicyService&MockInterface $policy */
        $policy = $this->mock(PlanChangePolicyService::class);

        $endpoint = new SubscriptionUpgradeEndpoint(
            $this->account_repository,
            $this->subscription_repository,
            $this->plan_repository,
            $this->billing_service,
            null,
            $policy
        );

        /** @var \WP_REST_Request&MockInterface $request */
        $request = $this->mock(\WP_REST_Request::class);
        $request->shouldReceive('get_json_params')->once()->andReturnUsing(static fn(): array => [
            'account_id' => 14,
            'plan_code' => 'trial',
            'payment_method' => 'stripe',
        ]);

        $this->account_repository->shouldReceive('get_by_id')->once()->with(14)->andReturnUsing(static fn(): array => ['id' => 14]);

        $plan = Plan::fromArray([
            'id' => 1,
            'plan_key' => 'trial',
        ]);
        $this->plan_repository->shouldReceive('getByCode')->once()->with('trial')->andReturn($plan);

        $policy->shouldReceive('canChangeToPlan')
            ->once()
            ->with(14, $plan)
            ->andReturnUsing(static fn(): array => [
                'allowed' => false,
                'message' => 'You cannot downgrade to the Trial plan.',
            ]);

        $this->billing_service->shouldReceive('requestPlanChange')->never();

        $response = $endpoint->handle($request);

        $this->assertSame(422, $response->get_status());
        $this->assertFalse((bool) $response->get_data()['success']);
    }
}
