<?php

namespace MYVH\Tests\Unit\Network;

use Brain\Monkey\Functions;
use MYVH\Login\PasswordValidator;
use MYVH\Network\CreateSiteRequestValidator;
use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class CreateSiteRequestValidatorTest extends UnitTestCase {
    private CreateSiteRequestValidator $validator;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'is_email' => static fn($value) => is_string($value) && str_contains($value, '@'),
            'is_wp_error' => static fn($value) => $value instanceof WP_Error,
        ]);

        $this->validator = new CreateSiteRequestValidator(new PasswordValidator());
    }

    /** @test */
    public function validate_returns_shared_password_error_for_invalid_admin_password(): void {
        $result = $this->validator->validate([
            'site_name' => 'Test Site',
            'subdomain' => 'test-site',
            'admin_email' => 'admin@example.com',
            'admin_first_name' => 'Test',
            'admin_last_name' => 'User',
            'admin_password' => 'password1!',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('Password must include at least one uppercase letter.', $result->get_error_message());
    }

    /** @test */
    public function validate_accepts_hyphenated_subdomain(): void {
        Functions\stubs([
            'is_multisite' => true,
            'is_subdomain_install' => true,
            'get_network' => (object) [
                'domain' => 'example.com',
                'id' => 1,
            ],
            'domain_exists' => false,
        ]);

        $result = $this->validator->validate([
            'site_name' => 'Test Site',
            'subdomain' => 'site-five',
            'admin_email' => 'admin@example.com',
            'admin_first_name' => 'Test',
            'admin_last_name' => 'User',
            'admin_password' => 'Password1!',
        ]);

        $this->assertTrue($result);
    }

    /** @test */
    public function validate_rejects_subdomain_starting_with_hyphen(): void {
        $result = $this->validator->validate([
            'site_name' => 'Test Site',
            'subdomain' => '-sitefive',
            'admin_email' => 'admin@example.com',
            'admin_first_name' => 'Test',
            'admin_last_name' => 'User',
            'admin_password' => 'Password1!',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('Site path cannot begin or end with a hyphen.', $result->get_error_message());
    }
}