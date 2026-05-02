<?php

namespace MYVH\Tests\Unit\Email;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Email\EmailService;
use MYVH\Email\PasswordSetupEmailService;
use MYVH\Login\PasswordResetHandler;
use MYVH\Tests\Unit\UnitTestCase;

class PasswordSetupEmailServiceTest extends UnitTestCase
{
    /** @var EmailService&\Mockery\MockInterface */
    private $email_service;
    /** @var PasswordResetHandler&\Mockery\MockInterface */
    private $reset_handler;
    private PasswordSetupEmailService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->email_service = Mockery::mock(EmailService::class);
        $this->reset_handler = Mockery::mock(PasswordResetHandler::class);

        $this->service = new PasswordSetupEmailService($this->email_service, $this->reset_handler);

        Functions\stubs([
            'add_query_arg'  => fn($args, $base) => $base . '?' . http_build_query($args),
            'set_transient'  => true,
        ]);
    }

    // ── send_password_setup_email ─────────────────────────────────────────

    /** @test */
    public function it_returns_false_when_user_id_is_zero(): void
    {
        Functions\when('get_userdata')->justReturn(null);

        $result = $this->service->send_password_setup_email(1, 0);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_returns_false_when_user_not_found(): void
    {
        Functions\when('get_userdata')->justReturn(null);

        $result = $this->service->send_password_setup_email(1, 99);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_returns_false_when_user_has_no_email(): void
    {
        $user = Mockery::mock('WP_User');
        $user->user_email = '';
        Functions\when('get_userdata')->justReturn($user);

        $result = $this->service->send_password_setup_email(1, 5);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_sends_email_and_returns_true_on_success(): void
    {
        $user = Mockery::mock('WP_User');
        $user->user_email = 'alice@example.com';

        Functions\when('get_userdata')->justReturn($user);

        $this->reset_handler->shouldReceive('get_reset_page_url')
            ->once()
            ->andReturn('https://example.com/login/');

        $this->email_service->shouldReceive('get_branding')
            ->once()
            ->andReturn([
                'logo_url'  => 'https://example.com/logo.png',
                'site_name' => 'My Hall',
                'site_url'  => 'https://example.com',
            ]);

        $this->email_service->shouldReceive('send')
            ->once()
            ->with(Mockery::on(fn($args) =>
                $args['to'] === 'alice@example.com' &&
                $args['template'] === 'password-setup' &&
                str_contains($args['template_vars']['reset_url'], 'myvh_reset=1')
            ))
            ->andReturn(true);

        $result = $this->service->send_password_setup_email(1, 5);

        $this->assertTrue($result);
    }
}
