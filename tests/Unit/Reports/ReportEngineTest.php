<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Reports;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Portal\ClientAdminService;
use MYVH\Reports\Report;
use MYVH\Reports\ReportEngine;
use MYVH\Reports\ReportPermissionService;
use MYVH\Reports\ReportQueryBuilder;
use MYVH\Tests\Unit\UnitTestCase;

final class ReportEngineTest extends UnitTestCase {
    public function test_it_runs_and_caches_report_rows(): void {
        $builder = new ReportQueryBuilder();
        $definition = [
            'group_by' => ['organisation'],
            'aggregates' => [['field' => 'total', 'function' => 'SUM', 'alias' => 'total_revenue']],
            'filters' => [['field' => 'status', 'operator' => 'IN', 'value' => ['confirmed']]],
            'sort' => [['field' => 'organisation', 'direction' => 'DESC']],
            'limit' => 25,
            'offset' => 5,
        ];
        $compiled = $builder->build($definition, 'bookings', 'wp_');

        Functions\when('current_user_can')->justReturn(true);

        Functions\when('get_transient')->justReturn(false);
        Functions\expect('set_transient')
            ->once()
            ->with('myvh_report_' . md5($compiled['sql'] . serialize($compiled['params'])), [
                ['organisation' => 'Alpha', 'total_revenue' => '12.50'],
            ], 300);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')
            ->once()
            ->andReturn('prepared-sql');
        $wpdb->shouldReceive('get_results')
            ->once()
            ->with('prepared-sql', 'ARRAY_A')
            ->andReturn([
                ['organisation' => 'Alpha', 'total_revenue' => '12.50'],
            ]);

        $client_admin_service = Mockery::mock(ClientAdminService::class);
        $client_admin_service->shouldReceive('can_administer_blog')->never();

        $permission_service = new ReportPermissionService($client_admin_service, $builder);

        $engine = new ReportEngine($builder, $wpdb, $permission_service);
        $report = new Report(1, 'Revenue', '', Report::TYPE_USER, 'bookings', json_encode($definition) ?: '{}', null, '2026-05-25 10:00:00');

        $result = $engine->run($report, ['user_id' => 9]);

        $this->assertSame(1, $result['meta']['count']);
        $this->assertSame(25, $result['meta']['limit']);
        $this->assertSame(5, $result['meta']['offset']);
        $this->assertSame('Alpha', $result['rows'][0]['organisation']);
    }
}