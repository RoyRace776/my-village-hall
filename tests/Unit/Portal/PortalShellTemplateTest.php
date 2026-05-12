<?php

namespace MYVH\Tests\Unit\Portal;

use Brain\Monkey\Functions;
use MYVH\Tests\Unit\UnitTestCase;

class PortalShellTemplateTest extends UnitTestCase {
    protected function setUp(): void {
        parent::setUp();

        global $myvh_container;

        $myvh_container = new class {
            public function get(string $class) {
                return new class {
                    public function get_all(array $args = []): array {
                        return [];
                    }
                };
            }
        };

        Functions\stubs([
            'wp_get_current_user' => static function () {
                return (object) ['display_name' => 'Test User'];
            },
            'get_bloginfo' => static fn($show = '') => 'My Village Hall',
            'is_user_logged_in' => static fn() => true,
            'apply_filters' => static fn($tag, $value = null) => $value,
            'esc_html' => static fn($value) => (string) $value,
            'esc_attr' => static fn($value) => (string) $value,
            'esc_attr_e' => static function ($value, $domain = null) {
                echo (string) $value;
            },
            'esc_url' => static fn($value) => (string) $value,
            'wp_logout_url' => static fn($redirect = '') => '/logout',
        ]);
    }

    /** @test */
    public function account_menu_shows_multi_site_count_and_links(): void {
        $accessible_sites = [
            [
                'name' => 'Alpha Hall',
                'url' => '/portal/alpha/',
                'is_current' => true,
            ],
            [
                'name' => 'Beta Hall',
                'url' => '/portal/beta/',
                'is_current' => false,
            ],
        ];
        $is_client_admin = false;
        $has_customer = false;
        $portal_logout_url = '/logout';
        $portal_branding = [
            'site_title' => 'My Village Hall',
            'logo_url' => '',
        ];

        ob_start();
        include MYVH_PLUGIN_DIR . 'templates/Portal/portal-shell.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Test User [2]', $html);
        $this->assertStringContainsString('myvh-portal-account-menu', $html);
        $this->assertStringContainsString('Alpha Hall', $html);
        $this->assertStringContainsString('Beta Hall', $html);
        $this->assertStringNotContainsString('myvh-portal-site-link', $html);
    }
}