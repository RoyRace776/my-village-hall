<?php

namespace MYVH\Tests\Unit\Network;

use Brain\Monkey\Functions;
use MYVH\Network\CreateSiteRequestValidator;
use MYVH\Network\SiteProvisioningRepository;
use MYVH\Network\SiteProvisioningService;
use MYVH\Network\WpSiteCloner;
use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class SiteProvisioningServiceTest extends UnitTestCase {
    private $validator;
    private $cloner;
    private $repo;
    private SiteProvisioningService $service;

    /** @var array<string,mixed> */
    private array $transients = [];

    protected function setUp(): void {
        parent::setUp();

        if (!defined('HOUR_IN_SECONDS')) {
            define('HOUR_IN_SECONDS', 3600);
        }

        $this->validator = $this->mock(CreateSiteRequestValidator::class);
        $this->cloner = $this->mock(WpSiteCloner::class);
        $this->repo = $this->mock(SiteProvisioningRepository::class);

        $this->service = new SiteProvisioningService(
            $this->validator,
            $this->cloner,
            $this->repo
        );

        Functions\stubs([
            'is_wp_error' => static fn($value): bool => $value instanceof WP_Error,
            'wp_hash_password' => static fn($value): string => 'hash:' . (string) $value,
            'current_time' => static fn(): string => '2026-01-01 00:00:00',
            'set_transient' => function (string $key, $value, int $ttl): bool {
                $this->transients[$key] = $value;
                return true;
            },
            'get_transient' => function (string $key) {
                return $this->transients[$key] ?? false;
            },
            'delete_transient' => function (string $key): bool {
                unset($this->transients[$key]);
                return true;
            },
            'home_url' => static fn(string $path = ''): string => 'https://example.test' . $path,
            'add_query_arg' => static fn(array $args, string $url): string => $url . '?' . http_build_query($args),
            'esc_url_raw' => static fn($url): string => (string) $url,
            'wp_validate_redirect' => static fn($url, $fallback = ''): string => (string) $url,
            'wp_parse_url' => static fn($url, $component = -1) => parse_url((string) $url, $component),
            'remove_query_arg' => static fn($keys, string $url): string => preg_replace('/\?.*$/', '', $url) ?: $url,
            'sanitize_text_field' => static fn($value): string => (string) $value,
            'sanitize_title' => static fn($value): string => strtolower(trim((string) $value)),
            'sanitize_user' => static fn($value, $strict = false): string => preg_replace('/[^a-z0-9_\-]/i', '', (string) $value) ?: '',
            'sanitize_email' => static fn($value): string => (string) $value,
            'get_site_option' => static fn($key, $default = []) => ['template_site_id' => 0],
            'get_user_by' => static fn($field, $value) => false,
            'username_exists' => static fn($username): bool => false,
            'wp_create_user' => static fn($username, $password, $email): int => 201,
            'wp_update_user' => static fn(array $payload): int => (int) ($payload['ID'] ?? 0),
            'get_site_url' => static fn(int $blog_id): string => 'https://site-' . $blog_id . '.example.test',
            'do_action' => static function (...$args): void {
            },
            'get_bloginfo' => static fn(string $key): string => $key === 'name' ? 'MyVH' : '',
            'wp_specialchars_decode' => static fn($value): string => (string) $value,
            'esc_html' => static fn($value): string => (string) $value,
            'esc_url' => static fn($value): string => (string) $value,
            'wp_strip_all_tags' => static fn($value): string => (string) $value,
            'wp_mail' => static fn($to, $subject, $body, $headers = []): bool => true,
            'get_network' => static fn() => (object) ['domain' => 'example.test', 'path' => '/', 'id' => 1],
            'is_subdomain_install' => static fn(): bool => true,
            '__' => static fn($text): string => (string) $text,
        ]);
    }

    /** @test */
    public function submit_returns_validator_error_message(): void {
        $this->validator->shouldReceive('validate')
            ->once()
            ->andReturn(new WP_Error('validation', 'Bad input'));

        $this->repo->shouldReceive('create')->never();

        $result = $this->service->submit([
            'site_name' => 'Test',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('Bad input', $result['message']);
    }

    /** @test */
    public function submit_stores_request_and_returns_success(): void {
        $this->validator->shouldReceive('validate')->once()->andReturn(true);

        $this->repo->shouldReceive('create')
            ->once()
            ->with(\Mockery::on(static function (array $record): bool {
                return str_starts_with((string) ($record['token'] ?? ''), '')
                    && ($record['subdomain'] ?? '') === 'hall-one'
                    && ($record['site_name'] ?? '') === 'Hall One'
                    && ($record['admin_email'] ?? '') === ''
                    && str_starts_with((string) ($record['admin_password'] ?? ''), 'hash:');
            }))
            ->andReturn(12);

        $result = $this->service->submit([
            'site_name' => 'Hall One',
            'subdomain' => 'hall-one',
            'admin_email' => '',
            'admin_first_name' => 'A',
            'admin_last_name' => 'B',
            'admin_password' => 'Password1!',
            'myvh_request_page_url' => '/request-site',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('Check your email to continue.', $result['message']);
        $this->assertCount(1, $this->transients);
    }

    /** @test */
    public function verify_and_provision_returns_error_when_transient_missing(): void {
        $result = $this->service->verify_and_provision('missing-token');

        $this->assertFalse($result['ok']);
        $this->assertSame('Invalid or expired link.', $result['message']);
    }

    /** @test */
    public function verify_and_provision_returns_not_found_when_row_missing(): void {
        $token = 'abc123';
        $this->transients['myvh_site_request_' . $token] = true;

        $this->repo->shouldReceive('find_by_token')->once()->with($token)->andReturn(null);
        $this->repo->shouldReceive('update_status')->never();

        $result = $this->service->verify_and_provision($token);

        $this->assertFalse($result['ok']);
        $this->assertSame('Provisioning record not found.', $result['message']);
        $this->assertArrayNotHasKey('myvh_site_request_' . $token, $this->transients);
    }

    /** @test */
    public function cancel_request_deletes_pending_request_and_transient(): void {
        $token = 'cancel-me';
        $this->transients['myvh_site_request_' . $token] = true;

        $this->repo->shouldReceive('find_by_token')
            ->once()
            ->with($token)
            ->andReturnUsing(static fn(): array => [
                'id' => 9,
                'site_name' => 'Hall A',
                'subdomain' => 'hall-a',
                'admin_email' => 'admin@example.test',
                'admin_first_name' => 'First',
                'admin_last_name' => 'Last',
            ]);

        $this->repo->shouldReceive('delete')->once()->with(9);

        $result = $this->service->cancel_request($token);

        $this->assertTrue($result['ok']);
        $this->assertSame('Your site request has been cancelled.', $result['message']);
        $this->assertSame('hall-a', $result['details']['subdomain']);
        $this->assertArrayNotHasKey('myvh_site_request_' . $token, $this->transients);
    }

    /** @test */
    public function verify_and_provision_clones_site_when_verified_row_is_valid(): void {
        Functions\when('get_site_option')->alias(static fn($key, $default = []) => ['template_site_id' => 5]);

        $token = 'ok-token';
        $this->transients['myvh_site_request_' . $token] = true;

        $row = [
            'id' => 42,
            'subdomain' => 'hall-b',
            'site_name' => 'Hall B',
            'admin_email' => '',
            'admin_first_name' => 'Admin',
            'admin_last_name' => 'User',
            'admin_password' => 'Password1!',
            'logo_url' => '',
        ];

        $this->repo->shouldReceive('find_by_token')->once()->with($token)->andReturnUsing(static fn(): array => $row);
        $this->repo->shouldReceive('update_status')->once()->with(42, 'verified');
        $this->repo->shouldReceive('update_status')->once()->with(42, 'cloning');
        $this->repo->shouldReceive('update_status')->once()->with(42, 'site cloned', \Mockery::on(static function (array $extra): bool {
            return \intval($extra['blog_id'] ?? 0) === 44 && \intval($extra['user_id'] ?? 0) === 201;
        }));

        $this->cloner->shouldReceive('clone')
            ->once()
            ->with(5, \Mockery::on(static fn(array $site): bool => ($site['name'] ?? '') === 'hall-b'), \Mockery::on(static fn(array $ctx): bool => \intval($ctx['provision_id'] ?? 0) === 42))
            ->andReturn(44);

        $result = $this->service->verify_and_provision($token);

        $this->assertTrue($result['ok']);
        $this->assertSame('Site has been cloned.', $result['message']);
        $this->assertSame('https://site-44.example.test', $result['site_url']);
        $this->assertSame(44, $result['details']['blog_id']);
    }
}
