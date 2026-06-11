<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Email\Mailer;

use Brain\Monkey\Functions;
use MYVH\Email\Mailer\SmtpTransport;
use MYVH\Settings\EmailServiceSettings;
use MYVH\Tests\Unit\UnitTestCase;

class SmtpTransportTest extends UnitTestCase {
    private int $hook_registration_count = 0;
    private int $mail_send_count = 0;

    protected function setUp(): void {
        parent::setUp();

        $reflection = new \ReflectionClass(SmtpTransport::class);
        $configured = $reflection->getProperty('configured');
        $configured->setAccessible(true);
        $configured->setValue(null, false);

        Functions\when('add_action')->alias(function (string $hook): void {
            if ($hook === 'phpmailer_init') {
                $this->hook_registration_count++;
            }
        });

        Functions\when('wp_mail')->alias(function (): bool {
            $this->mail_send_count++;
            return true;
        });
    }

    /** @test */
    public function it_registers_phpmailer_hook_only_once_across_multiple_sends(): void {
        $settings = $this->mock(EmailServiceSettings::class);

        $transport = new SmtpTransport($settings);

        $sent_first = $transport->send('first@example.test', 'Subject A', 'Body A');
        $sent_second = $transport->send('second@example.test', 'Subject B', 'Body B');

        $this->assertTrue($sent_first);
        $this->assertTrue($sent_second);
        $this->assertSame(1, $this->hook_registration_count);
        $this->assertSame(2, $this->mail_send_count);
    }

    /** @test */
    public function configure_mailer_applies_smtp_settings(): void {
        $settings = $this->mock(EmailServiceSettings::class);
        $settings->shouldReceive('get')->andReturnUsing(
            static function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'smtp_host' => 'smtp.example.test',
                    'smtp_port' => '2525',
                    'smtp_username' => 'mailer-user',
                    'smtp_password' => 'mailer-pass',
                    'smtp_encryption' => 'ssl',
                    default => $default,
                };
            }
        );

        $transport = new SmtpTransport($settings);
        $mailer = new class {
            public bool $smtp_called = false;
            public string $Host = '';
            public int $Port = 0;
            public bool $SMTPAuth = false;
            public string $Username = '';
            public string $Password = '';
            public string $SMTPSecure = '';

            public function isSMTP(): void {
                $this->smtp_called = true;
            }
        };

        $transport->configureMailer($mailer);

        $this->assertTrue($mailer->smtp_called);
        $this->assertSame('smtp.example.test', $mailer->Host);
        $this->assertSame(2525, $mailer->Port);
        $this->assertTrue($mailer->SMTPAuth);
        $this->assertSame('mailer-user', $mailer->Username);
        $this->assertSame('mailer-pass', $mailer->Password);
        $this->assertSame('ssl', $mailer->SMTPSecure);
    }
}
