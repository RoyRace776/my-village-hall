<?php

namespace MYVH\Tests\Unit\Login;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Customers\CustomerService;
use MYVH\Login\CustomerEmailVerificationService;
use MYVH\Login\LoginHandler;
use MYVH\Login\PasswordValidator;
use MYVH\Tests\Unit\UnitTestCase;

class LoginHandlerTestDouble extends LoginHandler
{
    public bool $redirect_called = false;

    protected function redirect_after_login_success(): void
    {
        $this->redirect_called = true;
    }
}

class LoginHandlerTest extends UnitTestCase
{
    protected function tearDown(): void
    {
        $_POST = [];
        unset($GLOBALS['myvh_container']);

        parent::tearDown();
    }

    /** @test */
    public function handle_login_blocks_unverified_customer_and_resends_verification_email(): void
    {
        $_POST = [
            'myvh_login_nonce' => 'valid-nonce',
            'username' => 'alex@example.com',
            'password' => 'Secret!123',
        ];

        $user = (object) [
            'ID' => 321,
            'user_email' => 'alex@example.com',
            'display_name' => 'Alex',
        ];

        $verification_service = Mockery::mock(CustomerEmailVerificationService::class);
        $verification_service->shouldReceive('send_verification_email')
            ->once()
            ->with(55, 321, 'alex@example.com', 'Alex')
            ->andReturn(true);

        $customer_service = Mockery::mock(CustomerService::class);
        $customer_service->shouldReceive('get_by_user_id')
            ->once()
            ->with(321)
            ->andReturn([
                'Id' => 55,
                'Email' => 'alex@example.com',
                'Name' => 'Alex',
                'EmailVerified' => 0,
            ]);

        $GLOBALS['myvh_container'] = new class($customer_service) {
            public function __construct(private $customer_service) {}

            public function get(string $class)
            {
                return $this->customer_service;
            }
        };

        Functions\when('myvh_setting')->justReturn(true);

        Functions\when('wp_verify_nonce')->justReturn(true);
        Functions\when('sanitize_text_field')->justReturn('alex@example.com');
        Functions\when('wp_signon')->justReturn($user);
        Functions\when('is_wp_error')->justReturn(false);

        Functions\when('wp_logout')->justReturn(null);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('__')->returnArg();

        $handler = new LoginHandlerTestDouble(new PasswordValidator(), $verification_service);
        $handler->handle_login();

        $this->assertFalse($handler->redirect_called);
    }
}
