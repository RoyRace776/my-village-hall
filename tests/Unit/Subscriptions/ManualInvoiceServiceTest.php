<?php

namespace MYVH\Tests\Unit\Subscriptions;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Customers\CustomerRepository;
use MYVH\Invoices\InvoiceService;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SettingsRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\ManualInvoiceService;
use MYVH\Tests\Unit\UnitTestCase;

class ManualInvoiceServiceTest extends UnitTestCase {
    /** @var SubscriptionRepository&MockInterface */
    private $subscription_repository;

    /** @var PlanRepository&MockInterface */
    private $plan_repository;

    /** @var AccountRepository&MockInterface */
    private $account_repository;

    /** @var SettingsRepository&MockInterface */
    private $settings_repository;

    /** @var InvoiceService&MockInterface */
    private $invoice_service;

    /** @var CustomerRepository&MockInterface */
    private $customer_repository;

    private ManualInvoiceService $service;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            '__' => fn($value) => (string) $value,
            'sanitize_email' => fn($value) => (string) $value,
            'sanitize_key' => fn($value) => strtolower((string) $value),
            'current_time' => fn($type = 'mysql') => $type === 'mysql' ? '2026-05-21 10:00:00' : 1716285600,
            'wp_json_encode' => fn($value) => json_encode($value),
        ]);

        /** @var SubscriptionRepository&MockInterface $subscription_repository */
        $subscription_repository = $this->mock(SubscriptionRepository::class);
        $this->subscription_repository = $subscription_repository;

        /** @var PlanRepository&MockInterface $plan_repository */
        $plan_repository = $this->mock(PlanRepository::class);
        $this->plan_repository = $plan_repository;

        /** @var AccountRepository&MockInterface $account_repository */
        $account_repository = $this->mock(AccountRepository::class);
        $this->account_repository = $account_repository;

        /** @var SettingsRepository&MockInterface $settings_repository */
        $settings_repository = $this->mock(SettingsRepository::class);
        $this->settings_repository = $settings_repository;

        /** @var InvoiceService&MockInterface $invoice_service */
        $invoice_service = $this->mock(InvoiceService::class);
        $this->invoice_service = $invoice_service;

        /** @var CustomerRepository&MockInterface $customer_repository */
        $customer_repository = $this->mock(CustomerRepository::class);
        $this->customer_repository = $customer_repository;

        $this->service = new ManualInvoiceService(
            $this->subscription_repository,
            $this->plan_repository,
            $this->account_repository,
            $this->settings_repository,
            $this->invoice_service,
            $this->customer_repository
        );
    }

    /** @test */
    public function create_pending_invoice_for_account_sets_pending_status_and_generates_invoice(): void {
        $this->account_repository->shouldReceive('get_by_id')->once()->with(7)->andReturnUsing(static fn(): array => [
            'id' => 7,
            'owner_user_id' => 22,
            'account_name' => 'Village Hall A',
            'contact_email' => 'billing@example.test',
        ]);

        $this->subscription_repository->shouldReceive('get_active_by_account_id')->once()->with(7)->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
            'id' => 99,
            'plan_code' => 'basic',
            'metadata' => '',
        ]));

        $this->plan_repository->shouldReceive('getByCode')->once()->with('basic')->andReturnUsing(static fn(): Plan => Plan::fromArray([
            'id' => 3,
            'price' => '49.00',
        ]));

        $this->customer_repository->shouldReceive('get_by_user_id')->once()->with(22)->andReturnUsing(static fn(): array => [
            'Id' => 501,
        ]);

        $this->settings_repository->shouldReceive('get_global_setting')->once()->with('grace_period_days', 7)->andReturn(5);

        $this->invoice_service->shouldReceive('save')
            ->once()
            ->withArgs(function (array $payload): bool {
                return (int) ($payload['customer_id'] ?? 0) === 501
                    && (float) ($payload['total_amount'] ?? 0) === 49.0
                    && (string) ($payload['status'] ?? '') === 'sent';
            })
            ->andReturn(7001);

        $this->invoice_service->shouldReceive('generate_pdf')->once()->with(7001)->andReturn('/tmp/invoice-7001.pdf');

        $this->subscription_repository->shouldReceive('update_by_id')
            ->once()
            ->withArgs(function (int $id, array $data): bool {
                return $id === 99
                    && (string) ($data['status'] ?? '') === 'pending_payment'
                    && str_contains((string) ($data['metadata'] ?? ''), 'manual_invoice_id');
            })
            ->andReturn(true);

        $result = $this->service->handlePaymentMethod(7, 'invoice');

        $this->assertSame(7001, $result);
    }

    /** @test */
    public function mark_invoice_paid_records_payment_and_sets_subscription_active_with_period_end(): void {
        $this->subscription_repository->shouldReceive('get_active_by_account_id')->once()->with(8)->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
            'id' => 109,
            'plan_code' => 'standard',
            'metadata' => '{"manual_invoice_id":7002}',
        ]));

        $this->invoice_service->shouldReceive('get')->once()->with(7002)->andReturnUsing(static fn(): array => [
            'Id' => 7002,
            'AmountDue' => '99.00',
        ]);

        $this->invoice_service->shouldReceive('record_payment')
            ->once()
            ->withArgs(function (int $invoice_id, float $amount, string $method): bool {
                return $invoice_id === 7002 && $amount === 99.0 && $method === 'transfer';
            })
            ->andReturn(1);

        $this->plan_repository->shouldReceive('getByCode')->once()->with('standard')->andReturnUsing(static fn(): Plan => Plan::fromArray([
            'id' => 10,
            'billing_interval' => 'monthly',
        ]));

        $this->subscription_repository->shouldReceive('update_by_id')
            ->once()
            ->withArgs(function (int $id, array $data): bool {
                return $id === 109
                    && (string) ($data['status'] ?? '') === 'active'
                    && !empty($data['current_period_end']);
            })
            ->andReturn(true);

        $result = $this->service->markInvoicePaid(8, 7002);

        $this->assertTrue($result);
    }
}
