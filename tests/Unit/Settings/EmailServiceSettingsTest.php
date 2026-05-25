<?php

namespace MYVH\Tests\Unit\Settings;

use MYVH\Settings\EmailServiceSettings;
use PHPUnit\Framework\TestCase;

class EmailServiceSettingsTest extends TestCase {
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
}
