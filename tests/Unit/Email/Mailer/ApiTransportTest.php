<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Email\Mailer;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Email\Mailer\ApiTransport;
use MYVH\Email\Mailer\MailTransport;
use MYVH\Settings\EmailServiceSettings;
use MYVH\Tests\Unit\UnitTestCase;
use Psr\Log\LoggerInterface;

class ApiTransportTest extends UnitTestCase {
    private int $remote_post_calls = 0;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'is_wp_error' => static fn($value): bool => $value instanceof \WP_Error,
            'wp_remote_retrieve_response_code' => static fn($response): int => (int) ($response['response']['code'] ?? 0),
            'wp_remote_retrieve_body' => static fn($response): string => (string) ($response['body'] ?? ''),
            'wp_strip_all_tags' => static fn($value): string => strip_tags((string) $value),
            'wp_json_encode' => static fn($value): string => (string) json_encode($value),
        ]);
    }

    /** @test */
    public function it_retries_once_then_falls_back_when_api_transport_fails(): void {
        $settings = $this->settingsFor('mailgun', [
            'api_key' => 'api-key',
            'api_domain' => 'mg.example.test',
            'api_fallback_to_wp_mail' => '1',
        ]);

        /** @var MailTransport&MockInterface $fallback_transport */
        $fallback_transport = $this->mock(MailTransport::class);
        $fallback_transport->shouldReceive('send')->once()->andReturn(true);

        /** @var LoggerInterface&MockInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->atLeast()->times(1);

        Functions\when('wp_remote_post')->alias(function (): \WP_Error|array {
            $this->remote_post_calls++;

            if ($this->remote_post_calls === 1) {
                return new \WP_Error('http_error', 'temporary network failure');
            }

            return [
                'response' => ['code' => 500],
                'body' => 'upstream failed',
            ];
        });

        $transport = new ApiTransport($settings, $logger, $fallback_transport);

        $sent = $transport->send(
            'to@example.test',
            'Subject',
            '<p>Hello</p>',
            [
                'From: Sender <sender@example.test>',
                'Content-Type: text/html; charset=UTF-8',
            ]
        );

        $this->assertTrue($sent);
        $this->assertSame(2, $this->remote_post_calls);
    }

    /** @test */
    public function it_maps_reply_to_for_mailgun_and_generates_text_fallback_from_html(): void {
        $settings = $this->settingsFor('mailgun', [
            'api_key' => 'api-key',
            'api_domain' => 'mg.example.test',
            'api_fallback_to_wp_mail' => '0',
        ]);

        $captured_url = '';
        $captured_args = [];

        Functions\when('wp_remote_post')->alias(function (string $url, array $args) use (&$captured_url, &$captured_args): array {
            $this->remote_post_calls++;
            $captured_url = $url;
            $captured_args = $args;

            return [
                'response' => ['code' => 200],
                'body' => 'ok',
            ];
        });

        $transport = new ApiTransport($settings);

        $sent = $transport->send(
            'to@example.test',
            'Subject',
            '<p>Hello<br>World</p>',
            [
                'From: Sender <sender@example.test>',
                'Reply-To: replies@example.test',
                'Content-Type: text/html; charset=UTF-8',
            ]
        );

        $this->assertTrue($sent);
        $this->assertSame(1, $this->remote_post_calls);
        $this->assertSame('https://api.mailgun.net/v3/mg.example.test/messages', $captured_url);
        $this->assertSame('replies@example.test', $captured_args['body']['h:Reply-To']);
        $this->assertSame('<p>Hello<br>World</p>', $captured_args['body']['html']);
        $this->assertSame("Hello\nWorld", $captured_args['body']['text']);
    }

    /** @test */
    public function it_maps_reply_to_for_sendgrid_payload(): void {
        $settings = $this->settingsFor('sendgrid', [
            'api_key' => 'sendgrid-key',
            'api_fallback_to_wp_mail' => '0',
        ]);

        $captured_payload = [];

        Functions\when('wp_remote_post')->alias(function (string $url, array $args) use (&$captured_payload): array {
            $this->remote_post_calls++;
            $this->assertSame('https://api.sendgrid.com/v3/mail/send', $url);

            $captured_payload = (array) json_decode((string) ($args['body'] ?? ''), true);

            return [
                'response' => ['code' => 202],
                'body' => 'accepted',
            ];
        });

        $transport = new ApiTransport($settings);

        $sent = $transport->send(
            'to@example.test',
            'Subject',
            '<p>Body</p>',
            [
                'From: Sender <sender@example.test>',
                'Reply-To: replies@example.test',
                'Content-Type: text/html; charset=UTF-8',
            ]
        );

        $this->assertTrue($sent);
        $this->assertSame('replies@example.test', $captured_payload['reply_to']['email'] ?? null);
        $this->assertSame('sender@example.test', $captured_payload['from']['email'] ?? null);
    }

    private function settingsFor(string $provider, array $overrides = []): EmailServiceSettings {
        $defaults = [
            'api_provider' => $provider,
            'api_key' => '',
            'api_domain' => '',
            'api_endpoint' => '',
            'api_fallback_to_wp_mail' => '1',
        ];

        $values = array_merge($defaults, $overrides);

        /** @var EmailServiceSettings&MockInterface $settings */
        $settings = $this->mock(EmailServiceSettings::class);
        $settings->shouldReceive('get')->andReturnUsing(
            static function (string $key, mixed $default = null) use ($values): mixed {
                return $values[$key] ?? $default;
            }
        );

        return $settings;
    }
}
