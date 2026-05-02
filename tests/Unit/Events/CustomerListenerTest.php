<?php

namespace MYVH\Tests\Unit\Events;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Events\CustomerListener;
use MYVH\Login\CustomerEmailVerificationService;
use MYVH\Tests\Unit\UnitTestCase;

class CustomerListenerTest extends UnitTestCase
{
    /** @var CustomerEmailVerificationService&\Mockery\MockInterface */
    private $verification_service;
    private CustomerListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verification_service = Mockery::mock(CustomerEmailVerificationService::class);
        $this->listener = new CustomerListener($this->verification_service);

        Functions\stubs([
            'is_email' => fn($v) => str_contains((string) $v, '@'),
        ]);
    }

    /** @test */
    public function register_hooks_customer_registered_event(): void
    {
        Functions\expect('add_action')
            ->once()
            ->with('myvh_event_customer.registered', Mockery::type('array'));

        $this->listener->register();
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function handler_sends_verification_email_for_valid_payload(): void
    {
        $this->verification_service->shouldReceive('send_verification_email')
            ->once()
            ->with(10, 20, 'alex@example.com', 'Alex');

        $this->listener->handle_customer_registered([
            'customer_id' => 10,
            'user_id' => 20,
            'email' => 'alex@example.com',
            'name' => 'Alex',
        ]);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function handler_ignores_invalid_payload(): void
    {
        $this->verification_service->shouldNotReceive('send_verification_email');

        $this->listener->handle_customer_registered([
            'customer_id' => 0,
            'user_id' => 20,
            'email' => 'alex@example.com',
            'name' => 'Alex',
        ]);

        $this->addToAssertionCount(1);
    }
}