<?php

namespace {
    if (!class_exists('wpdb')) {
        class wpdb {
        }
    }

    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }
}

namespace MYVH\Tests\Unit\Organisations {

use Mockery;
use MYVH\Organisations\OrganisationMemberRepository;
use MYVH\Tests\Unit\UnitTestCase;

class OrganisationMemberRepositoryTest extends UnitTestCase {
    public function test_get_admin_organisations_for_customer_excludes_system_organisations(): void {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';

        $preparedSql = null;

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::on(function ($sql) {
                return is_string($sql) && str_contains($sql, 'FROM wp_myvh_organisation_members om');
            }), 42)
            ->andReturnUsing(function ($sql, $customerId) {
                return str_replace('%d', (string) $customerId, $sql);
            });

        $wpdb->shouldReceive('get_results')
            ->once()
            ->with(Mockery::on(function ($sql) use (&$preparedSql) {
                $preparedSql = $sql;
                return true;
            }), ARRAY_A)
            ->andReturn([]);

        $repository = new OrganisationMemberRepository($wpdb);

        $result = $repository->get_admin_organisations_for_customer(42);

        $this->assertSame([], $result);
        $this->assertNotNull($preparedSql);
        $this->assertStringContainsString('AND om.IsOrganisationAdmin = 1', (string) $preparedSql);
        $this->assertStringContainsString('AND o.IsSystem = 0', (string) $preparedSql);
    }
}

}
