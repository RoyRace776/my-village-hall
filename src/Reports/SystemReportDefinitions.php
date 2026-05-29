<?php

declare(strict_types=1);

namespace MYVH\Reports;

final class SystemReportDefinitions {
    public const UPCOMING_BOOKINGS_ID = -1;
    public const REVENUE_BY_ORGANISATION_ID = -2;

    /**
     * @return array<int, Report>
     */
    public static function all(): array {
        $definitions = [
            [
                'id' => self::UPCOMING_BOOKINGS_ID,
                'name' => 'All Bookings',
                'description' => 'All bookings sorted by date.',
                'type' => Report::TYPE_SYSTEM,
                'data_source' => 'bookings',
                'query_json' => wp_json_encode([
                    'select' => ['booking_id', 'date', 'customer_name', 'room', 'organisation', 'total', 'status'],
                    'filters' => [],
                    'sort' => [
                        [
                            'field' => 'date',
                            'direction' => 'ASC',
                        ],
                    ],
                    'limit' => 1000,
                    'offset' => 0,
                ]) ?: '{}',
                'created_by' => null,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ],
            [
                'id' => self::REVENUE_BY_ORGANISATION_ID,
                'name' => 'Revenue by organisation',
                'description' => 'Total booking revenue grouped by organisation.',
                'type' => Report::TYPE_SYSTEM,
                'data_source' => 'booking_revenue_by_organisation',
                'query_json' => wp_json_encode([
                    'group_by' => ['organisation'],
                    'aggregates' => [
                        [
                            'field' => 'total',
                            'function' => 'SUM',
                            'alias' => 'total_revenue',
                        ],
                    ],
                    'filters' => [],
                    'sort' => [
                        [
                            'field' => 'total',
                            'direction' => 'DESC',
                        ],
                    ],
                    'limit' => 1000,
                    'offset' => 0,
                ]) ?: '{}',
                'created_by' => null,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ],
        ];

        $reports = [];
        foreach ($definitions as $definition) {
            $reports[(int) $definition['id']] = new Report(
                (int) $definition['id'],
                (string) $definition['name'],
                (string) $definition['description'],
                (string) $definition['type'],
                (string) $definition['data_source'],
                (string) $definition['query_json'],
                null,
                (string) $definition['created_at']
            );
        }

        return $reports;
    }
}
