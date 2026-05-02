<?php

namespace MYVH\Tests\Unit\Email;

use Brain\Monkey\Functions;
use MYVH\Email\EmailTemplateRegistry;
use MYVH\Tests\Unit\UnitTestCase;

class EmailTemplateRegistryTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubs(['get_bloginfo' => fn() => 'Test Site']);
    }

    // ── has / get ─────────────────────────────────────────────────────────

    /** @test */
    public function has_returns_true_for_known_templates(): void
    {
        $this->assertTrue(EmailTemplateRegistry::has('password-setup'));
        $this->assertTrue(EmailTemplateRegistry::has('password-reset'));
        $this->assertTrue(EmailTemplateRegistry::has('booking-confirmed'));
        $this->assertTrue(EmailTemplateRegistry::has('booking-cancelled'));
        $this->assertTrue(EmailTemplateRegistry::has('invoice'));
        $this->assertTrue(EmailTemplateRegistry::has('organisation-created'));
    }

    /** @test */
    public function has_returns_false_for_unknown_template(): void
    {
        $this->assertFalse(EmailTemplateRegistry::has('nonexistent-template'));
    }

    /** @test */
    public function get_returns_array_with_expected_keys(): void
    {
        $template = EmailTemplateRegistry::get('password-setup');

        $this->assertIsArray($template);
        $this->assertArrayHasKey('label', $template);
        $this->assertArrayHasKey('default_subject', $template);
        $this->assertArrayHasKey('placeholders', $template);
    }

    /** @test */
    public function get_returns_empty_array_for_unknown_slug(): void
    {
        $this->assertSame([], EmailTemplateRegistry::get('unknown'));
    }

    // ── default_subject ───────────────────────────────────────────────────

    /** @test */
    public function default_subject_returns_configured_subject(): void
    {
        $subject = EmailTemplateRegistry::default_subject('password-setup');

        $this->assertNotEmpty($subject);
        $this->assertIsString($subject);
    }

    /** @test */
    public function default_subject_returns_fallback_for_unknown_slug(): void
    {
        $subject = EmailTemplateRegistry::default_subject('unknown', 'My Fallback');

        $this->assertSame('My Fallback', $subject);
    }

    // ── replacement_map ───────────────────────────────────────────────────

    /** @test */
    public function replacement_map_returns_keyed_by_double_brace_tokens(): void
    {
        $map = EmailTemplateRegistry::replacement_map('password-setup', [
            'customer_name' => 'Alice',
            'reset_url'     => 'https://example.com/reset',
            'site_name'     => 'My Hall',
            'site_url'      => 'https://myhall.example',
            'logo_url'      => '',
        ]);

        $this->assertArrayHasKey('{{customer_name}}', $map);
        $this->assertSame('Alice', $map['{{customer_name}}']);
        $this->assertArrayHasKey('{{reset_url}}', $map);
        $this->assertArrayHasKey('{{year}}', $map);
    }

    /** @test */
    public function replacement_map_initialises_unknown_placeholders_to_empty_string(): void
    {
        // Pass vars without customer_name; it should still appear in map (from placeholder defs)
        $map = EmailTemplateRegistry::replacement_map('password-setup', []);

        $this->assertArrayHasKey('{{customer_name}}', $map);
        $this->assertSame('', $map['{{customer_name}}']);
    }

    /** @test */
    public function replacement_map_generates_logo_html_when_logo_url_provided(): void
    {
        \Brain\Monkey\Functions\stubs([
            'esc_url'  => fn($v) => (string) $v,
            'esc_attr' => fn($v) => (string) $v,
        ]);

        $map = EmailTemplateRegistry::replacement_map('password-setup', [
            'logo_url'  => 'https://example.com/logo.png',
            'site_name' => 'My Hall',
        ]);

        $this->assertStringContainsString('<img', $map['{{logo_html}}']);
        $this->assertStringContainsString('logo.png', $map['{{logo_html}}']);
    }

    /** @test */
    public function replacement_map_returns_empty_logo_html_when_no_logo_url(): void
    {
        $map = EmailTemplateRegistry::replacement_map('password-setup', ['logo_url' => '']);

        $this->assertSame('', $map['{{logo_html}}']);
    }

    // ── all ───────────────────────────────────────────────────────────────

    /** @test */
    public function all_returns_array_of_templates(): void
    {
        $all = EmailTemplateRegistry::all();

        $this->assertIsArray($all);
        $this->assertGreaterThan(3, count($all));
    }
}
