<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Email\Mailer;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Email\Mailer\MailTransport;
use MYVH\Email\Mailer\MailerService;
use MYVH\Settings\EmailServiceSettings;
use MYVH\Tests\Unit\UnitTestCase;

class MailerServiceTest extends UnitTestCase {
    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'sanitize_email' => static fn($value): string => trim((string) $value),
            'sanitize_text_field' => static fn($value): string => trim((string) $value),
            'is_email' => static fn($value): bool => is_string($value) && str_contains($value, '@'),
            'wp_specialchars_decode' => static fn($value): string => (string) $value,
            'get_bloginfo' => static fn(string $key = ''): string => $key === 'name' ? 'MyVH' : '',
            'wp_strip_all_tags' => static fn($value): string => strip_tags((string) $value),
        ]);
    }

    /** @test */
    public function it_adds_from_reply_to_and_generates_text_body_for_html_messages(): void {
        /** @var EmailServiceSettings&MockInterface $settings */
        $settings = $this->mock(EmailServiceSettings::class);
        $settings->shouldReceive('get')->andReturnUsing(
            static function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'email_from_address' => 'bot@example.test',
                    'email_from_name' => 'Site Bot',
                    'email_reply_to' => 'reply@example.test',
                    'api_provider' => 'mailgun',
                    default => $default,
                };
            }
        );

        /** @var MailTransport&MockInterface $transport */
        $transport = $this->mock(MailTransport::class);
        $transport->shouldReceive('send')
            ->once()
            ->withArgs(static function (string $to, string $subject, string $message, array $headers): bool {
                if ($to !== 'recipient@example.test' || $subject !== 'Hello' || $message !== '<p>Hello<br>World</p>') {
                    return false;
                }

                if (($headers['attachments'][0] ?? null) !== '/tmp/test.pdf') {
                    return false;
                }

                if (($headers['text_body'] ?? null) !== "Hello\nWorld") {
                    return false;
                }

                $has_content_type = in_array('Content-Type: text/html; charset=UTF-8', $headers, true);
                $has_from = in_array('From: Site Bot <bot@example.test>', $headers, true);
                $has_reply_to = in_array('Reply-To: reply@example.test', $headers, true);

                return $has_content_type && $has_from && $has_reply_to;
            })
            ->andReturn(true);

        $service = new MailerService($settings, $transport);

        $sent = $service->send(
            'recipient@example.test',
            'Hello',
            '<p>Hello<br>World</p>',
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'attachments' => ['/tmp/test.pdf'],
            ]
        );

        $this->assertTrue($sent);
    }
}
