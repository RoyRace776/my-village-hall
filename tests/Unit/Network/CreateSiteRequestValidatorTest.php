<?php

namespace MYVH\Tests\Unit\Network;

use Brain\Monkey\Functions;
use MYVH\Login\PasswordValidator;
use MYVH\Network\CreateSiteRequestValidator;
use MYVH\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Assert;
use WP_Error;

class CreateSiteRequestValidatorTest extends UnitTestCase {
    private CreateSiteRequestValidator $validator;

    protected function setUp(): void {
        parent::setUp();

        Functions\when('is_email')->alias(static fn($value) => is_string($value) && str_contains($value, '@'));
        Functions\when('is_wp_error')->alias(static fn($value) => $value instanceof WP_Error);

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
            'admin_password_confirm' => 'password1!',
        ]);

        Assert::assertInstanceOf(WP_Error::class, $result);
        Assert::assertSame('Password must include at least one uppercase letter.', $result->get_error_message());
    }

    /** @test */
    public function validate_accepts_hyphenated_subdomain(): void {
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('is_subdomain_install')->justReturn(true);
        Functions\when('get_network')->justReturn((object) [
                'domain' => 'example.com',
                'id' => 1,
            ]);
        Functions\when('domain_exists')->justReturn(false);

        $result = $this->validator->validate([
            'site_name' => 'Test Site',
            'subdomain' => 'site-five',
            'admin_email' => 'admin@example.com',
            'admin_first_name' => 'Test',
            'admin_last_name' => 'User',
            'admin_password' => 'Password1!',
            'admin_password_confirm' => 'Password1!',
        ]);

        Assert::assertTrue($result);
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
            'admin_password_confirm' => 'Password1!',
        ]);

        Assert::assertInstanceOf(WP_Error::class, $result);
        Assert::assertSame('Site path cannot begin or end with a hyphen.', $result->get_error_message());
    }

    /** @test */
    public function validate_accepts_valid_setup_payload(): void {
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('is_subdomain_install')->justReturn(true);
        Functions\when('get_network')->justReturn((object) [
            'domain' => 'example.com',
            'id' => 1,
        ]);
        Functions\when('domain_exists')->justReturn(false);

        $result = $this->validator->validate([
            'site_name' => 'Test Site',
            'subdomain' => 'site-five',
            'admin_email' => 'admin@example.com',
            'admin_first_name' => 'Test',
            'admin_last_name' => 'User',
            'admin_password' => 'Password1!',
            'admin_password_confirm' => 'Password1!',
            'setup_payload' => [
                'venue' => [
                    'name' => 'Village Hall',
                    'email' => 'bookings@example.com',
                ],
                'rooms' => [
                    ['key' => 'hall', 'name' => 'Main Hall'],
                    ['key' => 'room-2', 'name' => 'Meeting Room', 'capacity' => '40'],
                ],
                'pricing' => [
                    ['room_key' => 'hall', 'hourly_rate' => 20],
                    ['room_key' => 'room-2', 'hourly_rate' => 15.5],
                ],
                'addons' => [
                    ['name' => 'Projector', 'price' => '12.50'],
                ],
            ],
        ]);

        Assert::assertTrue($result);
    }

    /** @test */
    public function validate_rejects_setup_payload_without_venue_email(): void {
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('is_subdomain_install')->justReturn(true);
        Functions\when('get_network')->justReturn((object) [
            'domain' => 'example.com',
            'id' => 1,
        ]);
        Functions\when('domain_exists')->justReturn(false);

        $result = $this->validator->validate([
            'site_name' => 'Test Site',
            'subdomain' => 'site-five',
            'admin_email' => 'admin@example.com',
            'admin_first_name' => 'Test',
            'admin_last_name' => 'User',
            'admin_password' => 'Password1!',
            'admin_password_confirm' => 'Password1!',
            'setup_payload' => [
                'venue' => [
                    'name' => 'Village Hall',
                ],
                'rooms' => [
                    ['key' => 'hall', 'name' => 'Main Hall'],
                ],
                'pricing' => [
                    ['room_key' => 'hall', 'hourly_rate' => 20],
                ],
            ],
        ]);

        Assert::assertInstanceOf(WP_Error::class, $result);
        Assert::assertSame('Venue email is required.', $result->get_error_message());
    }

    /** @test */
    public function validate_rejects_setup_payload_when_any_room_is_missing_pricing(): void {
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('is_subdomain_install')->justReturn(true);
        Functions\when('get_network')->justReturn((object) [
            'domain' => 'example.com',
            'id' => 1,
        ]);
        Functions\when('domain_exists')->justReturn(false);

        $result = $this->validator->validate([
            'site_name' => 'Test Site',
            'subdomain' => 'site-five',
            'admin_email' => 'admin@example.com',
            'admin_first_name' => 'Test',
            'admin_last_name' => 'User',
            'admin_password' => 'Password1!',
            'admin_password_confirm' => 'Password1!',
            'setup_payload' => [
                'venue' => [
                    'name' => 'Village Hall',
                    'email' => 'bookings@example.com',
                ],
                'rooms' => [
                    ['key' => 'hall', 'name' => 'Main Hall'],
                    ['key' => 'meeting', 'name' => 'Meeting Room'],
                ],
                'pricing' => [
                    ['room_key' => 'hall', 'hourly_rate' => 20],
                ],
            ],
        ]);

        Assert::assertInstanceOf(WP_Error::class, $result);
        Assert::assertSame('Hourly pricing is required for every room.', $result->get_error_message());
    }

    /** @test */
    public function validate_rejects_when_admin_password_confirmation_does_not_match(): void {
        $result = $this->validator->validate([
            'site_name' => 'Test Site',
            'subdomain' => 'site-five',
            'admin_email' => 'admin@example.com',
            'admin_first_name' => 'Test',
            'admin_last_name' => 'User',
            'admin_password' => 'Password1!',
            'admin_password_confirm' => 'Password2!',
        ]);

        Assert::assertInstanceOf(WP_Error::class, $result);
        Assert::assertSame('Admin password and confirmation do not match.', $result->get_error_message());
    }
}