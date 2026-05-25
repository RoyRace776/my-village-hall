<?php

declare(strict_types=1);

namespace MYVH\Reports;

use MYVH\Reports\ReportDefinition;

final class ReportQueryBuilder {
    private const MAX_LIMIT = 1000;

    /**
     * @var array<int, string>
     */
    private const ALLOWED_AGGREGATES = ['SUM', 'COUNT', 'AVG', 'MIN', 'MAX'];

    private array $sourceSchema = [
        'bookings' => [
            'label' => 'Bookings',
            'table' => 'myvh_bookings',
            'fields' => [
                'booking_id' => [
                    'label' => 'Booking ID',
                    'column' => 'booking_id',
                    'type' => 'int',
                    'operators' => ['=', '!=', '<', '>', '<=', '>=', 'IN', 'BETWEEN'],
                ],
                'date' => [
                    'label' => 'Date',
                    'column' => 'date',
                    'type' => 'date',
                    'operators' => ['=', '!=', '<', '>', '<=', '>=', 'IN', 'BETWEEN'],
                ],
                'organisation' => [
                    'label' => 'Organisation',
                    'column' => 'organisation',
                    'type' => 'string',
                    'operators' => ['=', '!=', 'LIKE', 'IN'],
                ],
                'total' => [
                    'label' => 'Total',
                    'column' => 'total',
                    'type' => 'float',
                    'operators' => ['=', '!=', '<', '>', '<=', '>=', 'IN', 'BETWEEN'],
                ],
                'status' => [
                    'label' => 'Status',
                    'column' => 'status',
                    'type' => 'string',
                    'operators' => ['=', '!=', 'IN'],
                    'case_insensitive' => true,
                ],
            ],
        ],
        'booking_revenue_by_organisation' => [
            'label' => 'Revenue by Organisation',
            'table' => 'myvh_bookings',
            'fields' => [
                'organisation' => [
                    'label' => 'Organisation',
                    'column' => 'organisation',
                    'type' => 'string',
                    'operators' => ['=', '!=', 'LIKE', 'IN'],
                ],
                'total' => [
                    'label' => 'Total',
                    'column' => 'total',
                    'type' => 'float',
                    'operators' => ['=', '!=', '<', '>', '<=', '>=', 'IN', 'BETWEEN'],
                ],
            ],
        ],
    ];

    /**
     * @return array<int, string>
     */
    public function get_data_sources(): array {
        return array_keys($this->sourceSchema);
    }

    /**
     * @return array<string, mixed>
     */
    public function get_schema_for_source(string $data_source): array {
        if (!isset($this->sourceSchema[$data_source])) {
            throw new \InvalidArgumentException('Unknown report data source.');
        }

        $schema = $this->sourceSchema[$data_source];

        return [
            'source' => $data_source,
            'label' => (string) ($schema['label'] ?? $data_source),
            'table' => (string) ($schema['table'] ?? ''),
            'fields' => $this->normalize_fields($schema['fields'] ?? []),
            'operators' => $this->allowed_operators(),
            'aggregateFunctions' => self::ALLOWED_AGGREGATES,
            'sortDirections' => ['ASC', 'DESC'],
            'maxLimit' => self::MAX_LIMIT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get_schema(): array {
        $sources = [];

        foreach ($this->get_data_sources() as $source) {
            $sources[] = $this->get_schema_for_source($source);
        }

        return [
            'sources' => $sources,
            'operators' => $this->allowed_operators(),
            'aggregateFunctions' => self::ALLOWED_AGGREGATES,
            'sortDirections' => ['ASC', 'DESC'],
            'maxLimit' => self::MAX_LIMIT,
        ];
    }

    /**
     * @param mixed $definition
     * @return array{sql: string, params: array<int, mixed>}
     */
    public function build(mixed $definition, string $data_source, string $table_prefix): array {
        if (!isset($this->sourceSchema[$data_source])) {
            throw new \InvalidArgumentException('Unknown report data source.');
        }

        $schema = $this->get_schema_for_source($data_source);
        $report_definition = $definition instanceof ReportDefinition
            ? $definition
            : ReportDefinition::fromArray(is_array($definition) ? $definition : [], $schema);

        $base_sql = $this->base_query_for_source($data_source, $table_prefix);

        $select_sql = $this->build_select($report_definition, $schema);
        $where = $this->build_where($report_definition, $schema);
        $group_by = $this->build_group_by($report_definition, $schema);
        $order_by = $this->build_order_by($report_definition, $schema);
        [$limit_sql, $limit_params] = $this->build_limit_offset($report_definition);

        $sql = "SELECT {$select_sql} FROM ({$base_sql}) AS src";
        $params = [];

        if ($where['sql'] !== '') {
            $sql .= ' WHERE ' . $where['sql'];
            $params = array_merge($params, $where['params']);
        }

        if ($group_by !== '') {
            $sql .= ' GROUP BY ' . $group_by;
        }

        if ($order_by !== '') {
            $sql .= ' ORDER BY ' . $order_by;
        }

        $sql .= ' ' . $limit_sql;
        $params = array_merge($params, $limit_params);

        return [
            'sql' => trim($sql),
            'params' => $params,
        ];
    }

    /**
     * @param ReportDefinition $definition
     * @param array<string, mixed> $schema
     */
    private function build_select(ReportDefinition $definition, array $schema): string {
        $select_fields = $definition->getSelect();
        if ($select_fields === [] && !$definition->hasAggregates()) {
            throw new \InvalidArgumentException('Report definition must include select fields.');
        }

        $parts = [];
        foreach ($select_fields as $field) {
            $field_config = $this->get_schema_field_config($schema, $field);
            $parts[] = 'src.' . $this->quote_identifier((string) $field_config['column']) . ' AS ' . $this->quote_identifier($field);
        }

        foreach ($definition->getAggregates() as $aggregate) {
            $field_config = $this->get_schema_field_config($schema, $aggregate['field']);
            $function = $aggregate['function'];
            $column = $this->quote_identifier((string) $field_config['column']);

            $parts[] = sprintf('%s(src.%s) AS %s', $function, $column, $this->quote_identifier($aggregate['alias']));
        }

        return implode(', ', array_values(array_unique($parts)));
    }

    /**
     * @param ReportDefinition $definition
     * @param array<string, mixed> $schema
     * @return array{sql: string, params: array<int, mixed>}
     */
    private function build_where(ReportDefinition $definition, array $schema): array {
        $clauses = [];
        $params = [];

        foreach ($definition->getFilters() as $filter) {
            $field_config = $this->get_schema_field_config($schema, $filter['field']);
            $column = 'src.' . $this->quote_identifier((string) $field_config['column']);
            $is_case_insensitive = !empty($field_config['case_insensitive']);
            $operator = $filter['operator'];
            $value = $filter['value'];

            if ($is_case_insensitive && in_array($operator, ['=', '!=', 'LIKE', 'IN'], true)) {
                $column = 'LOWER(' . $column . ')';
            }

            if ($operator === 'IN') {
                $placeholders = implode(', ', array_fill(0, count((array) $value), '%s'));
                $clauses[] = $column . ' IN (' . $placeholders . ')';
                foreach ((array) $value as $item) {
                    $params[] = $is_case_insensitive && is_string($item) ? strtolower($item) : $item;
                }
                continue;
            }

            if ($operator === 'BETWEEN') {
                $clauses[] = $column . ' BETWEEN %s AND %s';
                $params[] = $value[0];
                $params[] = $value[1];
                continue;
            }

            if ($operator === 'LIKE' && $is_case_insensitive && is_string($value)) {
                $value = strtolower($value);
            }

            $clauses[] = $column . ' ' . $operator . ' %s';
            $params[] = $value;
        }

        return [
            'sql' => implode(' AND ', $clauses),
            'params' => $params,
        ];
    }

    /**
     * @param ReportDefinition $definition
     * @param array<string, mixed> $schema
     */
    private function build_group_by(ReportDefinition $definition, array $schema): string {
        $fields = $definition->getGroupBy();
        if ($fields === []) {
            return '';
        }

        $parts = [];
        foreach ($fields as $field) {
            $field_config = $this->get_schema_field_config($schema, $field);
            $parts[] = 'src.' . $this->quote_identifier((string) $field_config['column']);
        }

        return implode(', ', array_values(array_unique($parts)));
    }

    /**
     * @param ReportDefinition $definition
     * @param array<string, mixed> $schema
     */
    private function build_order_by(ReportDefinition $definition, array $schema): string {
        $sort = $definition->getSort();
        if ($sort === []) {
            return '';
        }

        $parts = [];
        foreach ($sort as $item) {
            $field_config = $this->get_schema_field_config($schema, $item['field']);
            $direction = strtoupper($item['direction'] ?? 'ASC');
            $parts[] = 'src.' . $this->quote_identifier((string) $field_config['column']) . ' ' . ($direction === 'DESC' ? 'DESC' : 'ASC');
        }

        return implode(', ', $parts);
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function build_limit_offset(ReportDefinition $definition): array {
        $limit = min(self::MAX_LIMIT, max(1, $definition->getLimit()));
        $offset = max(0, $definition->getOffset());

        return ['LIMIT %d OFFSET %d', [$limit, $offset]];
    }

    private function base_query_for_source(string $data_source, string $table_prefix): string {
        if ($data_source === 'bookings') {
            return "
                SELECT
                    b.Id AS `booking_id`,
                    b.StartDate AS `date`,
                    COALESCE(o.Name, '') AS `organisation`,
                    COALESCE(ch.TotalAmount, 0) AS `total`,
                    b.Status AS `status`
                FROM {$table_prefix}myvh_bookings b
                LEFT JOIN {$table_prefix}myvh_organisations o ON b.OrganisationId = o.Id
                LEFT JOIN (
                    SELECT BookingId, SUM(TotalAmount) AS TotalAmount
                    FROM {$table_prefix}myvh_booking_charges
                    GROUP BY BookingId
                ) ch ON b.Id = ch.BookingId
            ";
        }

        if ($data_source === 'booking_revenue_by_organisation') {
            return "
                SELECT
                    COALESCE(o.Name, 'Unassigned') AS `organisation`,
                    COALESCE(SUM(ch.TotalAmount), 0) AS `total`
                FROM {$table_prefix}myvh_bookings b
                LEFT JOIN {$table_prefix}myvh_organisations o ON b.OrganisationId = o.Id
                LEFT JOIN {$table_prefix}myvh_booking_charges ch ON b.Id = ch.BookingId
                WHERE LOWER(b.Status) IN ('confirmed', 'completed')
                GROUP BY COALESCE(o.Name, 'Unassigned')
            ";
        }

        throw new \InvalidArgumentException('Unknown report data source.');
    }

    private function quote_identifier(string $identifier): string {
        return '`' . str_replace('`', '', $identifier) . '`';
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<int, array<string, mixed>>
     */
    private function normalize_fields(array $fields): array {
        $normalized = [];

        foreach ($fields as $name => $config) {
            $field_config = is_array($config) ? $config : [];
            $operators = $field_config['operators'] ?? [];

            $normalized[] = [
                'name' => (string) $name,
                'column' => (string) ($field_config['column'] ?? $name),
                'label' => (string) ($field_config['label'] ?? $name),
                'type' => (string) ($field_config['type'] ?? 'string'),
                'operators' => array_values(array_filter(
                    is_array($operators) ? $operators : [],
                    static fn(mixed $operator): bool => is_string($operator) && $operator !== ''
                )),
                'case_insensitive' => !empty($field_config['case_insensitive']),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function get_schema_field_config(array $schema, string $field): array {
        foreach ((array) ($schema['fields'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ((string) ($item['name'] ?? '') === $field) {
                return $item;
            }
        }

        throw new \InvalidArgumentException("Unknown report field: {$field}");
    }

    /**
     * @return array<int, string>
     */
    private function allowed_operators(): array {
        return ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'IN', 'BETWEEN'];
    }
}
