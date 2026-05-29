<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Reports;

use MYVH\Reports\ReportQueryBuilder;
use MYVH\Tests\Unit\UnitTestCase;

final class ReportQueryBuilderTest extends UnitTestCase {
    public function test_it_builds_grouped_aggregate_sql_with_safe_placeholders(): void {
        $builder = new ReportQueryBuilder();

        $result = $builder->build([
            'group_by' => ['organisation'],
            'aggregates' => [
                ['field' => 'total', 'function' => 'SUM', 'alias' => 'total_revenue'],
            ],
            'filters' => [
                ['field' => 'status', 'operator' => 'IN', 'value' => ['confirmed']],
            ],
            'sort' => [
                ['field' => 'organisation', 'direction' => 'DESC'],
            ],
            'limit' => 25,
            'offset' => 5,
        ], 'bookings', 'wp_');

        $this->assertStringContainsString('SELECT src.`organisation` AS `organisation`, SUM(src.`total`) AS `total_revenue`', $result['sql']);
        $this->assertStringContainsString('GROUP BY src.`organisation`', $result['sql']);
        $this->assertStringContainsString('ORDER BY src.`organisation` DESC', $result['sql']);
        $this->assertStringContainsString('LIMIT %d OFFSET %d', $result['sql']);
        $this->assertSame(['confirmed', 25, 5], $result['params']);
    }

    public function test_bookings_source_supports_customer_name_field(): void {
        $builder = new ReportQueryBuilder();

        $schema = $builder->get_schema_for_source('bookings');
        $field_names = array_map(static fn(array $field): string => (string) ($field['name'] ?? ''), $schema['fields']);

        $this->assertContains('customer_name', $field_names);
        $this->assertContains('room', $field_names);

        $result = $builder->build([
            'select' => ['booking_id', 'customer_name', 'room'],
            'sort' => [
                ['field' => 'customer_name', 'direction' => 'ASC'],
            ],
            'limit' => 10,
            'offset' => 0,
        ], 'bookings', 'wp_');

        $this->assertStringContainsString('src.`customer_name` AS `customer_name`', $result['sql']);
        $this->assertStringContainsString('src.`room` AS `room`', $result['sql']);
        $this->assertStringContainsString('ORDER BY src.`customer_name` ASC', $result['sql']);
        $this->assertSame([10, 0], $result['params']);
    }
}