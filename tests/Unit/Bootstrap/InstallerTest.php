<?php

namespace {
    if (!class_exists('wpdb')) {
        class wpdb {}
    }
    if (!class_exists('WP_User')) {
        class WP_User {}
    }
}

namespace MYVH\Tests\Unit\Bootstrap {

use Brain\Monkey;
use MYVH\Bootstrap\Installer;
use MYVH\Tests\Unit\UnitTestCase;
use Mockery;

class InstallerTest extends UnitTestCase {

    /** @var \Mockery\MockInterface&\wpdb */
    private $wpdb;

    protected function setUp(): void {
        parent::setUp();

        // Mock the global wpdb object
        $this->wpdb = Mockery::mock('wpdb');
        $this->wpdb->prefix = 'wp_';

        // Stub prepare() to return the query as-is
        $this->wpdb->shouldReceive('prepare')
            ->andReturnUsing(fn($query, ...$args) => $query)
            ->byDefault();

        // Setup default stubs for WordPress functions
        Monkey\Functions\stubs([
            'get_users' => [],
            'get_super_admins' => [],
            'get_user_by' => null,
            'is_multisite' => false,
            'sanitize_text_field' => fn($v) => (string) $v,
            'sanitize_email' => fn($v) => (string) $v,
        ]);
    }

    /** @test */
    public function it_creates_system_customer_when_none_exists_single_site(): void {
        global $wpdb;
        $wpdb = $this->wpdb;

        // Mock: no system customer exists
        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->with(Mockery::type('string'), 'ARRAY_A')
            ->andReturn(null);

        // Mock: get_users returns an admin
        Monkey\Functions\when('get_users')
            ->justReturn([$this->create_mock_user(1, 'Admin User', 'admin@example.com')]);

        // Mock: insert should be called
        $this->wpdb->shouldReceive('insert')
            ->once()
            ->with(
                'wp_myvh_customers',
                Mockery::on(fn($data) =>
                    $data['Name'] === 'Admin User'
                    && $data['Email'] === 'admin@example.com'
                    && $data['IsSystem'] === 1
                    && isset($data['WPUserId'])
                    && $data['WPUserId'] === 1
                )
            );

        Installer::add_system_customer();

        // Verify the Mockery expectations were called
        $this->assertTrue(true);
    }

    /** @test */
    public function it_updates_existing_system_customer_with_admin_details(): void {
        global $wpdb;
        $wpdb = $this->wpdb;

        // Mock: system customer exists
        $existing_customer = ['Id' => 5, 'Name' => 'System', 'Email' => '', 'IsSystem' => 1];
        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->with(Mockery::type('string'), 'ARRAY_A')
            ->andReturn($existing_customer);

        // Mock: get_users returns an admin
        Monkey\Functions\when('get_users')
            ->justReturn([$this->create_mock_user(2, 'New Admin', 'newadmin@example.com')]);

        // Mock: update should be called
        $this->wpdb->shouldReceive('update')
            ->once()
            ->with(
                'wp_myvh_customers',
                Mockery::on(fn($data) =>
                    $data['Name'] === 'New Admin'
                    && $data['Email'] === 'newadmin@example.com'
                    && $data['WPUserId'] === 2
                ),
                ['Id' => 5]
            );

        Installer::add_system_customer();

        // Verify the Mockery expectations were called
        $this->assertTrue(true);
    }

    /** @test */
    public function it_falls_back_to_system_name_when_no_admin_found(): void {
        global $wpdb;
        $wpdb = $this->wpdb;

        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->with(Mockery::type('string'), 'ARRAY_A')
            ->andReturn(null);

        // Return no users
        Monkey\Functions\when('get_users')->justReturn([]);

        $this->wpdb->shouldReceive('insert')
            ->once()
            ->with(
                'wp_myvh_customers',
                Mockery::on(fn($data) =>
                    $data['Name'] === 'System'
                    && $data['Email'] === ''
                    && !isset($data['WPUserId'])
                )
            );

        Installer::add_system_customer();

        // Verify the Mockery expectations were called
        $this->assertTrue(true);
    }

    /** @test */
    public function it_selects_first_super_admin_on_multisite(): void {
        global $wpdb;
        $wpdb = $this->wpdb;

        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->with(Mockery::type('string'), 'ARRAY_A')
            ->andReturn(null);

        // Mock multisite environment
        Monkey\Functions\when('is_multisite')->justReturn(true);

        // Mock: first super admin is 'super_admin_user'
        Monkey\Functions\when('get_super_admins')->justReturn(['super_admin_user', 'another_admin']);

        $super_admin = $this->create_mock_user(10, 'Super Admin', 'super@example.com');
        Monkey\Functions\when('get_user_by')
            ->justReturn($super_admin);

        $this->wpdb->shouldReceive('insert')
            ->once()
            ->with(
                'wp_myvh_customers',
                Mockery::on(fn($data) =>
                    $data['Name'] === 'Super Admin'
                    && $data['Email'] === 'super@example.com'
                    && $data['WPUserId'] === 10
                )
            );

        Installer::add_system_customer();

        // Verify the Mockery expectations were called
        $this->assertTrue(true);
    }

    // ────────────────────────────────────────────────────────────────────

    /**
     * Create a mock WP_User object.
     */
    private function create_mock_user(int $id, string $display_name, string $email): \WP_User {
        $user = Mockery::mock('WP_User');
        $user->ID = $id;
        $user->display_name = $display_name;
        $user->user_email = $email;
        $user->user_login = strtolower(str_replace(' ', '_', $display_name));

        return $user;
    }
}
}
