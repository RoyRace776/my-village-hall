<?php

namespace MYVH\Tests\Unit\Settings;

use Brain\Monkey\Functions;
use MYVH\Settings\EmailServiceSettings;
use PHPUnit\Framework\TestCase;

class EmailServiceSettingsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'sanitize_text_field' => static fn($value): string => trim((string) $value),
            'sanitize_email' => static fn($value): string => trim((string) $value),
            'esc_url_raw' => static fn($value): string => trim((string) $value),
            'absint' => static fn($value): int => max(0, (int) $value),
            'boolval' => static fn($value): bool => (bool) $value,
            'is_multisite' => static fn(): bool => true,
            'get_option' => static fn(string $key, mixed $default = null): mixed => $default,
            'get_site_option' => static fn(string $key, mixed $default = null): mixed => $default,
            'update_option' => static fn(string $key, mixed $value): bool => true,
            'update_site_option' => static fn(string $key, mixed $value): bool => true,
        ]);
    }

    /** @test */
    public function schema_exposes_transport_sender_smtp_and_api_fields(): void {
        $settings = new EmailServiceSettings();
        $schema = $settings->schema();

        $this->assertSame('email-service', $settings->tab()['key']);
        $this->assertArrayHasKey('sender', $schema);
        $this->assertArrayHasKey('transport', $schema);
        $this->assertArrayHasKey('smtp', $schema);
        $this->assertArrayHasKey('api', $schema);

        $this->assertArrayHasKey('mail_transport', $schema['transport']['fields']);
        $this->assertArrayHasKey('smtp_host', $schema['smtp']['fields']);
        $this->assertArrayHasKey('api_provider', $schema['api']['fields']);

        $this->assertSame('wp_mail', $schema['transport']['fields']['mail_transport']['default']);
        $this->assertSame('mailgun', $schema['api']['fields']['api_provider']['default']);
        $this->assertTrue($schema['api']['fields']['api_fallback_to_wp_mail']['default']);
    }

    /** @test */
    public function save_persists_to_the_current_site_option_storage(): void {
        $writes = [];

        Functions\when('get_option')->alias(static fn(string $key, mixed $default = null): mixed => $default);
        Functions\when('get_site_option')->alias(static fn(string $key, mixed $default = null): mixed => $default);
        Functions\when('update_option')->alias(static function (string $key, mixed $value) use (&$writes): bool {
            $writes['site'][] = [$key, $value];
            return true;
        });
        Functions\when('update_site_option')->alias(static function (string $key, mixed $value) use (&$writes): bool {
            $writes['network'][] = [$key, $value];
            return true;
        });

        $settings = new EmailServiceSettings();
        $settings->save([
            'email_from_address' => '  bot@example.test ',
            'email_from_name' => '  Site Bot ',
            'email_reply_to' => ' reply@example.test ',
            'mail_transport' => 'smtp',
            'smtp_host' => ' smtp.example.test ',
            'smtp_port' => '2525',
            'smtp_username' => ' user ',
            'smtp_password' => ' pass ',
            'smtp_encryption' => 'ssl',
            'api_provider' => 'mailgun',
            'api_key' => ' key ',
            'api_domain' => ' mg.example.test ',
            'api_endpoint' => ' https://api.example.test ',
            'api_fallback_to_wp_mail' => '1',
        ]);

        $this->assertArrayHasKey('site', $writes);
        $this->assertArrayNotHasKey('network', $writes);
        $this->assertSame('myvh_email_service_settings', $writes['site'][0][0]);
    }
}
