<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Reports;

use MYVH\Reports\ReportDefinition;
use MYVH\Tests\Unit\UnitTestCase;

final class ReportDefinitionTest extends UnitTestCase {
    public function test_it_accepts_legacy_fields_key_for_select(): void {
        $schema = [
            'fields' => [
                ['name' => 'organisation', 'column' => 'organisation', 'type' => 'string', 'operators' => ['=']],
            ],
        ];

        $definition = ReportDefinition::fromArray([
            'fields' => ['organisation'],
        ], $schema);

        $this->assertSame(['organisation'], $definition->getSelect());
    }

    public function test_it_normalizes_legacy_definition_shape(): void {
        $schema = [
            'fields' => [
                ['name' => 'organisation', 'column' => 'organisation', 'type' => 'string', 'operators' => ['=']],
                ['name' => 'total', 'column' => 'total', 'type' => 'float', 'operators' => ['=', '>=', 'BETWEEN']],
            ],
        ];

        $definition = ReportDefinition::fromArray([
            'select' => ['organisation'],
            'filters' => [
                ['field' => 'total', 'operator' => '>=', 'value' => '10.50'],
            ],
            'order_by' => [
                ['field' => 'organisation', 'direction' => 'desc'],
            ],
            'limit' => 1500,
            'offset' => -2,
        ], $schema);

        $this->assertSame(['organisation'], $definition->getSelect());
        $this->assertSame([['field' => 'organisation', 'direction' => 'DESC']], $definition->getSort());
        $this->assertSame(1000, $definition->getLimit());
        $this->assertSame(0, $definition->getOffset());
    }

    public function test_it_rejects_unknown_fields(): void {
        $schema = [
            'fields' => [
                ['name' => 'organisation', 'column' => 'organisation', 'type' => 'string', 'operators' => ['=']],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown report field: missing');

        ReportDefinition::fromArray([
            'select' => ['missing'],
        ], $schema);
    }
}