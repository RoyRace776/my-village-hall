<?php

namespace MYVH\Tests\Unit\Network;

use Brain\Monkey;
use MYVH\Tests\Unit\UnitTestCase;

class CreateSiteFormTemplateTest extends UnitTestCase {
    /** @test */
    public function submitted_confirmation_shows_subdirectory_style_address_when_not_subdomain_install(): void {
        $html = $this->renderSubmittedConfirmation([
            'is_subdomain' => false,
            'network_domain' => 'example.com',
            'network_path' => '/network/',
            'form_values' => [
                'subdomain' => 'newsite',
            ],
        ]);

        $this->assertTrue(str_contains($html, 'example.com/network/newsite'));
        $this->assertFalse(str_contains($html, 'newsite.example.com'));
    }

    /** @test */
    public function submitted_confirmation_shows_subdomain_style_address_when_subdomain_install(): void {
        $html = $this->renderSubmittedConfirmation([
            'is_subdomain' => true,
            'network_domain' => 'example.com',
            'network_path' => '/network/',
            'form_values' => [
                'subdomain' => 'newsite',
            ],
        ]);

        $this->assertTrue(str_contains($html, 'newsite.example.com'));
        $this->assertFalse(str_contains($html, 'example.com/network/newsite'));
    }

    private function renderSubmittedConfirmation(array $overrides = []): string {
        Monkey\Functions\stubs([
            'sanitize_title' => static fn($value) => strtolower(trim((string) $value)),
            'trailingslashit' => static fn($value) => rtrim((string) $value, '/') . '/',
            'home_url' => static fn($path = '/') => 'https://example.com' . (string) $path,
            'esc_html__' => static fn($text, $domain = null) => (string) $text,
            'esc_attr__' => static fn($text, $domain = null) => (string) $text,
            'esc_html' => static fn($text) => (string) $text,
            'esc_attr' => static fn($text) => (string) $text,
            'esc_url' => static fn($url) => (string) $url,
            'remove_query_arg' => static fn($keys = null, $url = '') => (string) $url,
        ]);

        $state = ['status' => '', 'message' => '', 'site_url' => ''];
        $form_values = array_merge([
            'site_name' => 'My Site',
            'subdomain' => 'newsite',
            'admin_first_name' => 'Jane',
            'admin_last_name' => 'Doe',
            'admin_email' => 'jane@example.com',
        ], $overrides['form_values'] ?? []);
        $verification_result = false;
        $verification_action = 'verify';
        $submitted = true;
        $network_domain = (string) ($overrides['network_domain'] ?? 'example.com');
        $network_path = (string) ($overrides['network_path'] ?? '/');
        $is_subdomain = (bool) ($overrides['is_subdomain'] ?? false);
        $captcha_site_key = '';
        $request_page_url = 'https://example.com/create-site';

        ob_start();
        include MYVH_PLUGIN_DIR . 'templates/Network/create-site-form.php';
        return (string) ob_get_clean();
    }
}
