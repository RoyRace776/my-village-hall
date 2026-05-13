<?php

namespace MYVH\Tests\Unit\Portal\Support;

use Brain\Monkey\Functions;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\PortalAuth;
use MYVH\Tests\Unit\UnitTestCase;

class PortalAuthTest extends UnitTestCase {
    /** @test */
    public function require_user_returns_error_when_not_logged_in(): void {
        Functions\stubs([
            'is_user_logged_in' => false,
            'wp_send_json_error' => static function ($message, $status = 400): void {
                throw new \RuntimeException((string) $message . ':' . (int) $status);
            },
            'check_ajax_referer' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not logged in:401');

        PortalAuth::require_user();
    }

    /** @test */
    public function require_user_checks_nonce_when_logged_in(): void {
        Functions\stubs([
            'is_user_logged_in' => true,
        ]);

        Functions\expect('check_ajax_referer')
            ->once()
            ->with('myvh_portal', 'nonce');

        PortalAuth::require_user();

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function require_client_admin_returns_error_when_user_lacks_permission(): void {
        Functions\stubs([
            'is_user_logged_in' => true,
            'check_ajax_referer' => true,
            'get_current_user_id' => 15,
            'get_current_blog_id' => 3,
            'wp_send_json_error' => static function ($message, $status = 400): void {
                throw new \RuntimeException((string) $message . ':' . (int) $status);
            },
        ]);

        $client_admin_service = $this->mock(ClientAdminService::class);
        $client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(15, 3)
            ->andReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Permission denied:403');

        PortalAuth::require_client_admin($client_admin_service);
    }

    /** @test */
    public function require_client_admin_allows_access_when_user_is_client_admin(): void {
        Functions\stubs([
            'is_user_logged_in' => true,
            'check_ajax_referer' => true,
            'get_current_user_id' => 22,
            'get_current_blog_id' => 4,
            'wp_send_json_error' => static function ($message, $status = 400): void {
                throw new \RuntimeException((string) $message . ':' . (int) $status);
            },
        ]);

        $client_admin_service = $this->mock(ClientAdminService::class);
        $client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(22, 4)
            ->andReturn(true);

        PortalAuth::require_client_admin($client_admin_service);

        $this->addToAssertionCount(1);
    }
}
