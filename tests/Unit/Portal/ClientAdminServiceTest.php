<?php

namespace MYVH\Tests\Unit\Portal;

use Brain\Monkey\Functions;
use MYVH\Portal\ClientAdminService;
use MYVH\Tests\Unit\UnitTestCase;

class ClientAdminServiceTest extends UnitTestCase {

    /** @test */
    public function remove_all_assignments_for_blog_removes_deleted_site_entry(): void {
        Functions\stubs([
            'is_multisite' => true,
            'get_site_option' => [
                12 => [7],
                34 => [7, 9],
            ],
        ]);

        Functions\expect('update_site_option')
            ->once()
            ->with('myvh_client_admin_assignments', [
                12 => [7],
            ]);

        $service = new ClientAdminService();
        $service->remove_all_assignments_for_blog(34);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function get_accessible_sites_for_user_excludes_missing_or_deleted_sites(): void {
        Functions\stubs([
            'is_multisite' => true,
            'is_super_admin' => false,
            'user_can' => false,
            'get_site_option' => [
                12 => [7],
                34 => [7],
            ],
            'get_blogs_of_user' => [],
            'get_current_blog_id' => 12,
            'get_home_url' => static fn(int $blog_id, string $path = ''): string => '/site-' . $blog_id . $path,
            'trailingslashit' => static fn(string $value): string => rtrim($value, '/') . '/',
            'get_bloginfo' => static fn(string $field = ''): string => 'Fallback Hall',
            'get_site' => static function (int $blog_id) {
                if ($blog_id === 12) {
                    return (object) [
                        'blog_id' => 12,
                        'deleted' => 0,
                        'archived' => 0,
                        'spam' => 0,
                    ];
                }

                return false;
            },
            'get_blog_details' => static function (int $blog_id) {
                if ($blog_id === 12) {
                    return (object) [
                        'blogname' => 'Alpha Hall',
                    ];
                }

                return false;
            },
        ]);

        $service = new ClientAdminService();
        $sites = $service->get_accessible_sites_for_user(7);

        $this->assertCount(1, $sites);
        $this->assertSame(12, $sites[0]['blog_id']);
        $this->assertSame('Alpha Hall', $sites[0]['name']);
    }
}
