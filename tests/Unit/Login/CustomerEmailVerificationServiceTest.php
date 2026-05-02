<?php

namespace MYVH\Tests\Unit\Login;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Customers\CustomerRepository;
use MYVH\Email\EmailService;
use MYVH\Login\CustomerEmailVerificationService;
use MYVH\Tests\Unit\UnitTestCase;

class CustomerEmailVerificationServiceTest extends UnitTestCase
{
    /** @var EmailService&\Mockery\MockInterface */
    private $email_service;
    /** @var CustomerRepository&\Mockery\MockInterface */
    private $customer_repository;
    private CustomerEmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->email_service = Mockery::mock(EmailService::class);
        $this->customer_repository = Mockery::mock(CustomerRepository::class);

        $this->service = new CustomerEmailVerificationService(
            $this->email_service,
            $this->customer_repository,
            3600
        );

        Functions\stubs([
            'is_email' => fn($v) => str_contains((string) $v, '@'),
            'set_transient' => true,
            'get_transient' => null,
            'delete_transient' => true,
            'add_query_arg' => fn($args, $base) => $base . '?' . http_build_query($args),
            'apply_filters' => '',
            'get_pages' => [],
            'get_page_by_path' => null,
            'get_permalink' => 'https://example.com/login/',
            'home_url' => 'https://example.com/login/',
        ]);
    }

    /** @test */
    public function send_verification_email_sends_template_email(): void
    {
        $this->email_service->shouldReceive('get_branding')
            ->once()
            ->andReturn([
                'logo_url' => '',
                'site_name' => 'Village Hall',
                'site_url' => 'https://example.com',
            ]);

        $this->email_service->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function ($args) {
                return ($args['template'] ?? '') === 'customer-email-verification'
                    && ($args['to'] ?? '') === 'alex@example.com'
                    && str_contains((string) ($args['template_vars']['verification_url'] ?? ''), 'myvh_verify_email=1');
            }))
            ->andReturn(true);

        $result = $this->service->send_verification_email(10, 20, 'alex@example.com', 'Alex');

        $this->assertTrue($result);
    }

    /** @test */
    public function verify_token_returns_false_for_invalid_token(): void
    {
        Functions\when('get_transient')->justReturn('different-token');

        $result = $this->service->verify_token(20, 'abc123');

        $this->assertFalse($result);
    }

    /** @test */
    public function verify_token_marks_customer_verified_and_deletes_token(): void
    {
        Functions\when('get_transient')->justReturn('abc123');

        $this->customer_repository->shouldReceive('get_by_user_id')
            ->once()
            ->with(20)
            ->andReturn(['Id' => 5]);

        $this->customer_repository->shouldReceive('update')
            ->once()
            ->with(['EmailVerified' => 1], ['Id' => 5])
            ->andReturn(true);

        $result = $this->service->verify_token(20, 'abc123');

        $this->assertTrue($result);
    }
}