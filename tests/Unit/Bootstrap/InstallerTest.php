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
        $this->wpdb->options = 'wp_options';
        $this->wpdb->sitemeta = 'wp_sitemeta';

        // Stub prepare() to return the query as-is
        $this->wpdb->shouldReceive('prepare')
            ->andReturnUsing(fn($query, ...$args) => $query)
            ->byDefault();

        // Stub get_var() to return null by default (skips org membership insertion)
        $this->wpdb->shouldReceive('get_var')
            ->andReturn(null)
            ->byDefault();

        $this->wpdb->shouldReceive('query')
            ->andReturn(true)
            ->byDefault();

        $this->wpdb->shouldReceive('esc_like')
            ->andReturnUsing(fn($value) => $value)
            ->byDefault();

        // Setup default stubs for WordPress functions
        Monkey\Functions\stubs([
            'get_users' => [],
            'get_super_admins' => [],
            'get_user_by' => null,
            'is_multisite' => false,
            'sanitize_text_field' => fn($v) => (string) $v,
            'sanitize_email' => fn($v) => (string) $v,
            'get_option' => null,
            'update_option' => true,
            'delete_option' => true,
            'delete_site_option' => true,
        ]);
    }

    /** @test */
    public function maybe_upgrade_skips_when_version_is_current(): void {
        Monkey\Functions\when('get_option')->justReturn(Installer::DB_VERSION);
        Monkey\Functions\expect('update_option')->never();

        Installer::maybe_upgrade();

        $this->assertTrue(true);
    }

    /** @test */
    public function backfill_opening_hours_by_day_executes_two_insert_queries(): void {
        global $wpdb;
        $wpdb = $this->wpdb;

        $queries = [];

        $this->wpdb->shouldReceive('query')
            ->twice()
            ->andReturnUsing(function ($sql) use (&$queries) {
                $queries[] = $sql;
                return true;
            });

        Installer::backfill_opening_hours_by_day($this->wpdb);

        $this->assertCount(2, $queries);
        $this->assertStringContainsString('myvh_venue_hours', $queries[0]);
        $this->assertStringContainsString('myvh_room_hours', $queries[1]);
    }

    /** @test */
    public function drop_tables_runs_drop_statement_for_each_plugin_table(): void {
        global $wpdb;
        $wpdb = $this->wpdb;

        $queries = [];

        $this->wpdb->shouldReceive('query')
            ->times(22)
            ->andReturnUsing(function ($sql) use (&$queries) {
                $queries[] = $sql;
                return true;
            });

        Installer::drop_tables($this->wpdb);

        $this->assertCount(22, $queries);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_myvh_bookings', implode("\n", $queries));
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_myvh_room_hours', implode("\n", $queries));
    }

    /** @test */
    public function tidy_up_deletes_plugin_options_and_transients_on_single_site(): void {
        global $wpdb;
        $wpdb = $this->wpdb;

        $deleted_options = [];
        $deleted_site_options = [];

        $this->wpdb->shouldReceive('get_col')
            ->times(3)
            ->andReturnUsing((function () {
                $responses = [
                    ['myvh_setting_one'],
                    ['_transient_myvh_cache_a'],
                    ['_transient_timeout_myvh_cache_a'],
                ];

                return function () use (&$responses): array {
                    return array_shift($responses) ?? [];
                };
            })());

        Monkey\Functions\when('delete_option')->alias(function ($key) use (&$deleted_options) {
            $deleted_options[] = $key;
            return true;
        });

        Monkey\Functions\when('delete_site_option')->alias(function ($key) use (&$deleted_site_options) {
            $deleted_site_options[] = $key;
            return true;
        });

        Installer::tidy_up();

        $this->assertCount(3, $deleted_options);
        $this->assertCount(0, $deleted_site_options);
    }

    /** @test */
    public function add_personal_organisation_type_inserts_when_missing(): void {
        global $wpdb;
        $wpdb = $this->wpdb;

        $this->wpdb->insert_id = 44;

        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->with(Mockery::type('string'), 'ARRAY_A')
            ->andReturn(null);

        $this->wpdb->shouldReceive('insert')
            ->once()
            ->with('wp_myvh_organisation_types', Mockery::on(fn(array $data): bool =>
                ($data['Name'] ?? '') === 'Person'
                && \intval($data['IsSystem'] ?? 0) === 1
                && \intval($data['IsDefault'] ?? 0) === 0
            ));

        $type_id = Installer::add_personal_organisation_type($this->wpdb);

        $this->assertSame(44, $type_id);
    }

    /** @test */
    public function add_personal_organisation_type_updates_when_existing(): void {
        global $wpdb;
        $wpdb = $this->wpdb;

        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->with(Mockery::type('string'), 'ARRAY_A')
            ->andReturnUsing(static fn(): array => ['Id' => 12]);

        $this->wpdb->shouldReceive('update')
            ->once()
            ->with('wp_myvh_organisation_types', Mockery::on(fn(array $data): bool =>
                ($data['Description'] ?? '') === 'Individual person'
                && \intval($data['IsSystem'] ?? 0) === 1
                && \intval($data['IsDefault'] ?? 0) === 0
            ), ['Id' => 12]);

        $type_id = Installer::add_personal_organisation_type($this->wpdb);

        $this->assertSame(12, $type_id);
    }

    /** @test */
    public function add_personal_organisation_inserts_when_missing(): void {
        global $wpdb;
        $wpdb = $this->wpdb;

        $this->wpdb->insert_id = 51;

        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->with(Mockery::type('string'), 'ARRAY_A')
            ->andReturn(null);

        $this->wpdb->shouldReceive('insert')
            ->once()
            ->with('wp_myvh_organisations', Mockery::on(fn(array $data): bool =>
                ($data['Name'] ?? '') === 'Personal booking'
                && \intval($data['OrganisationTypeId'] ?? 0) === 7
                && \intval($data['IsDefault'] ?? 0) === 1
            ));

        $org_id = Installer::add_personal_organisation($this->wpdb, 7);

        $this->assertSame(51, $org_id);
    }

    /** @test */
    public function add_personal_organisation_returns_existing_id_when_present(): void {
        global $wpdb;
        $wpdb = $this->wpdb;

        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->with(Mockery::type('string'), 'ARRAY_A')
            ->andReturnUsing(static fn(): array => ['Id' => 99]);

        $this->wpdb->shouldNotReceive('insert');

        $org_id = Installer::add_personal_organisation($this->wpdb, 7);

        $this->assertSame(99, $org_id);
    }

    /** @test */
    public function add_default_organisation_type_inserts_when_none_exists(): void {
        global $wpdb;
        $wpdb = $this->wpdb;

        $this->wpdb->shouldReceive('get_var')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn(0);

        $this->wpdb->shouldReceive('insert')
            ->once()
            ->with('wp_myvh_organisation_types', Mockery::on(fn(array $data): bool =>
                ($data['Name'] ?? '') === 'Default'
                && \intval($data['IsDefault'] ?? 0) === 1
            ));

        Installer::add_default_organisation_type($this->wpdb);

        $this->assertTrue(true);
    }

    /** @test */
    public function add_default_organisation_type_normalizes_multiple_defaults(): void {
        global $wpdb;
        $wpdb = $this->wpdb;

        $this->wpdb->shouldReceive('get_var')
            ->twice()
            ->andReturn(2, 5);

        $this->wpdb->shouldReceive('query')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn(true);

        Installer::add_default_organisation_type($this->wpdb);

        $this->assertTrue(true);
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

        Installer::add_system_customer(1);

        // Verify the Mockery expectations were called
        $this->assertTrue(true);
    }

    /** @test */
    public function it_skips_insert_when_system_customer_already_exists(): void {
        global $wpdb;
        $wpdb = $this->wpdb;

        // Mock: system customer already exists
        $existing_customer = ['Id' => 5, 'Name' => 'System', 'Email' => '', 'IsSystem' => 1];
        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->with(Mockery::type('string'), 'ARRAY_A')
            ->andReturnUsing(fn() => $existing_customer);

        // Mock: get_users returns an admin (resolved but not used for update)
        Monkey\Functions\when('get_users')
            ->justReturn([$this->create_mock_user(2, 'New Admin', 'newadmin@example.com')]);

        // Neither insert nor update should be called for the customer table
        $this->wpdb->shouldNotReceive('insert');
        $this->wpdb->shouldNotReceive('update');

        Installer::add_system_customer(1);

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

        Installer::add_system_customer(1);

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

        Installer::add_system_customer(1);

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
