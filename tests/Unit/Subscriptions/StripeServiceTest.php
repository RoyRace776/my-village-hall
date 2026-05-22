<?php

namespace MYVH\Tests\Unit\Subscriptions;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Subscriptions\Repositories\SettingsRepository;
use MYVH\Subscriptions\Services\StripeService;
use MYVH\Tests\Unit\UnitTestCase;

class StripeServiceTest extends UnitTestCase {
    /** @var SettingsRepository&MockInterface */
    private $settings_repository;

    private StripeService $service;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'sanitize_email' => fn($value) => (string) $value,
            'sanitize_text_field' => fn($value) => (string) $value,
        ]);

        /** @var SettingsRepository&MockInterface $settings_repository */
        $settings_repository = $this->mock(SettingsRepository::class);
        $this->settings_repository = $settings_repository;

        $this->service = new class($this->settings_repository) extends StripeService {
            protected function getStripeClient(): \Stripe\StripeClient {
                throw new \RuntimeException('Stripe client should not be called in this test');
            }
        };
    }

    /** @test */
    public function create_customer_returns_empty_array_when_email_is_empty(): void {
        $customer = $this->service->createCustomer('', 'Village Hall');

        $this->assertSame([], $customer);
    }

    /** @test */
    public function create_subscription_returns_empty_array_when_customer_or_price_is_empty(): void {
        $this->assertSame([], $this->service->createSubscription('', 'price_basic_1'));
        $this->assertSame([], $this->service->createSubscription('cus_123', ''));
    }

    /** @test */
    public function cancel_subscription_returns_false_when_subscription_id_is_empty(): void {
        $this->assertFalse($this->service->cancelSubscription('  '));
    }
}