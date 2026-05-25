<?php

declare(strict_types=1);

namespace MYVH\Reports;

use JsonSerializable;

final class ReportDefinition implements JsonSerializable {
    public const MAX_LIMIT = 1000;
    public const DEFAULT_LIMIT = 1000;
    public const DEFAULT_OFFSET = 0;

    private const ALLOWED_AGGREGATES = ['SUM', 'COUNT', 'AVG', 'MIN', 'MAX'];
    private const DEFAULT_STRING_OPERATORS = ['=', '!=', 'LIKE', 'IN'];
    private const DEFAULT_NUMERIC_OPERATORS = ['=', '!=', '>', '<', '>=', '<=', 'IN', 'BETWEEN'];
    private const DEFAULT_DATE_OPERATORS = ['=', '!=', '>', '<', '>=', '<=', 'IN', 'BETWEEN'];

    /**
     * @var array<int, string>
     */
    private array $select = [];

    /**
     * @var array<int, array{field: string, operator: string, value: mixed}>
     */
    private array $filters = [];

    /**
     * @var array<int, string>
     */
    private array $group_by = [];

    /**
     * @var array<int, array{field: string, function: string, alias: string}>
     */
    private array $aggregates = [];

    /**
     * @var array<int, array{field: string, direction: string}>
     */
    private array $sort = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $schema_fields = [];

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $schema
     */
    public function __construct(array $definition, array $schema) {
        $this->schema_fields = $this->normalize_schema_fields($schema);
        $this->select = $this->normalize_field_list($definition['select'] ?? ($definition['fields'] ?? []), 'select');
        $this->group_by = $this->normalize_field_list($definition['group_by'] ?? [], 'group_by');
        $this->filters = $this->normalize_filters($definition['filters'] ?? []);
        $this->aggregates = $this->normalize_aggregates($definition['aggregates'] ?? []);
        $this->sort = $this->normalize_sort($definition['sort'] ?? ($definition['order_by'] ?? []));
        $this->limit = $this->normalize_limit($definition['limit'] ?? self::DEFAULT_LIMIT);
        $this->offset = $this->normalize_offset($definition['offset'] ?? self::DEFAULT_OFFSET);

        if ($this->select === [] && $this->group_by !== []) {
            $this->select = $this->group_by;
        }

        if ($this->aggregates !== [] && $this->group_by === [] && $this->select !== []) {
            $this->group_by = $this->select;
        }

        if ($this->aggregates !== [] && $this->select !== [] && $this->group_by !== []) {
            $missing_group_fields = array_values(array_diff($this->select, $this->group_by));
            if ($missing_group_fields !== []) {
                throw new \InvalidArgumentException('Select fields must be included in group_by when aggregates are used.');
            }
        }

        if ($this->select === [] && $this->aggregates === []) {
            throw new \InvalidArgumentException('Report definition must include select fields or aggregates.');
        }
    }

    /**
     * @var int
     */
    private int $limit = self::DEFAULT_LIMIT;

    /**
     * @var int
     */
    private int $offset = self::DEFAULT_OFFSET;

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $schema
     */
    public static function fromArray(array $definition, array $schema): self {
        return new self($definition, $schema);
    }

    /**
     * @return array<int, string>
     */
    public function getSelect(): array {
        return $this->select;
    }

    /**
     * @return array<int, array{field: string, operator: string, value: mixed}>
     */
    public function getFilters(): array {
        return $this->filters;
    }

    /**
     * @return array<int, string>
     */
    public function getGroupBy(): array {
        return $this->group_by;
    }

    /**
     * @return array<int, array{field: string, function: string, alias: string}>
     */
    public function getAggregates(): array {
        return $this->aggregates;
    }

    /**
     * @return array<int, array{field: string, direction: string}>
     */
    public function getSort(): array {
        return $this->sort;
    }

    public function getLimit(): int {
        return $this->limit;
    }

    public function getOffset(): int {
        return $this->offset;
    }

    public function hasAggregates(): bool {
        return $this->aggregates !== [];
    }

    public function hasGroupBy(): bool {
        return $this->group_by !== [];
    }

    public function hasSelect(): bool {
        return $this->select !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'select' => $this->select,
            'filters' => $this->filters,
            'group_by' => $this->group_by,
            'aggregates' => $this->aggregates,
            'sort' => $this->sort,
            'order_by' => $this->sort,
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array {
        return $this->toArray();
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, array<string, mixed>>
     */
    private function normalize_schema_fields(array $schema): array {
        $fields = $schema['fields'] ?? [];
        if (!is_array($fields)) {
            throw new \InvalidArgumentException('Report schema fields must be an array.');
        }

        $normalized = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $normalized[$name] = [
                'name' => $name,
                'column' => (string) ($field['column'] ?? $name),
                'label' => (string) ($field['label'] ?? $name),
                'type' => (string) ($field['type'] ?? 'string'),
                'operators' => array_values(array_filter(
                    is_array($field['operators'] ?? null) ? $field['operators'] : [],
                    static fn(mixed $operator): bool => is_string($operator) && $operator !== ''
                )),
                'case_insensitive' => !empty($field['case_insensitive']),
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $fields
     * @return array<int, string>
     */
    private function normalize_field_list(mixed $fields, string $context): array {
        if ($fields === null || $fields === []) {
            return [];
        }

        if (!is_array($fields)) {
            throw new \InvalidArgumentException("{$context} must be an array.");
        }

        $normalized = [];
        foreach ($fields as $field) {
            if (!is_string($field) || trim($field) === '') {
                throw new \InvalidArgumentException("{$context} fields must be non-empty strings.");
            }

            $field = trim($field);
            $this->get_schema_field($field);
            $normalized[] = $field;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param mixed $filters
     * @return array<int, array{field: string, operator: string, value: mixed}>
     */
    private function normalize_filters(mixed $filters): array {
        if ($filters === null || $filters === []) {
            return [];
        }

        if (!is_array($filters)) {
            throw new \InvalidArgumentException('filters must be an array.');
        }

        $normalized = [];
        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                throw new \InvalidArgumentException('Each filter must be an object.');
            }

            $field = $this->normalize_required_field((string) ($filter['field'] ?? ''), 'filter field');
            $operator = strtoupper((string) ($filter['operator'] ?? ''));
            if ($operator === '') {
                throw new \InvalidArgumentException('Each filter must include an operator.');
            }

            $field_config = $this->get_schema_field($field);
            $this->assert_operator_allowed($field, $field_config, $operator);

            $normalized[] = [
                'field' => $field,
                'operator' => $operator,
                'value' => $this->normalize_filter_value($field, $field_config, $operator, $filter['value'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $aggregates
     * @return array<int, array{field: string, function: string, alias: string}>
     */
    private function normalize_aggregates(mixed $aggregates): array {
        if ($aggregates === null || $aggregates === []) {
            return [];
        }

        if (!is_array($aggregates)) {
            throw new \InvalidArgumentException('aggregates must be an array.');
        }

        $normalized = [];
        foreach ($aggregates as $aggregate) {
            if (!is_array($aggregate)) {
                throw new \InvalidArgumentException('Each aggregate must be an object.');
            }

            $field = $this->normalize_required_field((string) ($aggregate['field'] ?? ''), 'aggregate field');
            $field_config = $this->get_schema_field($field);

            $function = strtoupper((string) ($aggregate['function'] ?? ''));
            if ($function === '' || !in_array($function, self::ALLOWED_AGGREGATES, true)) {
                throw new \InvalidArgumentException('Aggregates must use SUM, COUNT, AVG, MIN, or MAX.');
            }

            $this->assert_aggregate_allowed($field, $field_config, $function);

            $alias = (string) ($aggregate['alias'] ?? '');
            if ($alias === '') {
                $alias = strtolower($function . '_' . $field);
            }

            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
                throw new \InvalidArgumentException('Aggregate alias must be a safe identifier.');
            }

            $normalized[] = [
                'field' => $field,
                'function' => $function,
                'alias' => $alias,
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $sort
     * @return array<int, array{field: string, direction: string}>
     */
    private function normalize_sort(mixed $sort): array {
        if ($sort === null || $sort === []) {
            return [];
        }

        if (!is_array($sort)) {
            throw new \InvalidArgumentException('sort must be an array.');
        }

        $normalized = [];
        foreach ($sort as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Each sort item must be an object.');
            }

            $field = $this->normalize_required_field((string) ($item['field'] ?? ''), 'sort field');
            $this->get_schema_field($field);

            $direction = strtoupper((string) ($item['direction'] ?? 'ASC'));
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                $direction = 'ASC';
            }

            $normalized[] = [
                'field' => $field,
                'direction' => $direction,
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    private function normalize_limit(mixed $value): int {
        $limit = (int) $value;
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        return min(self::MAX_LIMIT, $limit);
    }

    /**
     * @param mixed $value
     */
    private function normalize_offset(mixed $value): int {
        $offset = (int) $value;
        if ($offset < 0) {
            $offset = self::DEFAULT_OFFSET;
        }

        return $offset;
    }

    private function normalize_required_field(string $field, string $context): string {
        $field = trim($field);
        if ($field === '') {
            throw new \InvalidArgumentException("{$context} is required.");
        }

        return $field;
    }

    /**
     * @return array<string, mixed>
     */
    private function get_schema_field(string $field): array {
        if (!isset($this->schema_fields[$field])) {
            throw new \InvalidArgumentException("Unknown report field: {$field}");
        }

        return $this->schema_fields[$field];
    }

    /**
     * @param array<string, mixed> $field_config
     */
    private function assert_operator_allowed(string $field, array $field_config, string $operator): void {
        $allowed_operators = $this->allowed_operators_for_field($field_config);
        if (!in_array($operator, $allowed_operators, true)) {
            throw new \InvalidArgumentException("Unsupported operator for field {$field}: {$operator}");
        }
    }

    /**
     * @param array<string, mixed> $field_config
     */
    private function assert_aggregate_allowed(string $field, array $field_config, string $function): void {
        $type = (string) ($field_config['type'] ?? 'string');
        if (in_array($function, ['SUM', 'AVG'], true) && !in_array($type, ['int', 'float', 'number'], true)) {
            throw new \InvalidArgumentException("Aggregate {$function} is only allowed on numeric fields like {$field}.");
        }
    }

    /**
     * @param array<string, mixed> $field_config
     * @return array<int, string>
     */
    private function allowed_operators_for_field(array $field_config): array {
        $operators = $field_config['operators'] ?? [];
        if (is_array($operators) && $operators !== []) {
            return array_values(array_filter(
                $operators,
                static fn(mixed $operator): bool => is_string($operator) && $operator !== ''
            ));
        }

        $type = (string) ($field_config['type'] ?? 'string');
        if (in_array($type, ['int', 'float', 'number'], true)) {
            return self::DEFAULT_NUMERIC_OPERATORS;
        }

        if (in_array($type, ['date', 'datetime'], true)) {
            return self::DEFAULT_DATE_OPERATORS;
        }

        return self::DEFAULT_STRING_OPERATORS;
    }

    /**
     * @param array<string, mixed> $field_config
     */
    private function normalize_filter_value(string $field, array $field_config, string $operator, mixed $value): mixed {
        $type = (string) ($field_config['type'] ?? 'string');
        $is_case_insensitive = !empty($field_config['case_insensitive']);

        if ($operator === 'IN') {
            if (is_string($value)) {
                $value = array_values(array_filter(array_map('trim', explode(',', $value)), static fn(string $item): bool => $item !== ''));
            }

            if (!is_array($value) || $value === []) {
                throw new \InvalidArgumentException("Operator IN requires a non-empty array value for field {$field}");
            }

            return array_values(array_map(function (mixed $item) use ($field, $type, $is_case_insensitive): mixed {
                return $this->normalize_scalar_filter_value($field, $type, $item, $is_case_insensitive);
            }, $value));
        }

        if ($operator === 'BETWEEN') {
            if (is_string($value)) {
                $value = array_values(array_filter(array_map('trim', explode(',', $value)), static fn(string $item): bool => $item !== ''));
            }

            if (!is_array($value) || count($value) !== 2) {
                throw new \InvalidArgumentException("Operator BETWEEN requires exactly two values for field {$field}");
            }

            $values = array_values($value);
            return [
                $this->normalize_scalar_filter_value($field, $type, $values[0], $is_case_insensitive),
                $this->normalize_scalar_filter_value($field, $type, $values[1], $is_case_insensitive),
            ];
        }

        if ($operator === 'LIKE' && !is_scalar($value) && $value !== null) {
            throw new \InvalidArgumentException("Operator LIKE requires a string value for field {$field}");
        }

        return $this->normalize_scalar_filter_value($field, $type, $value, $is_case_insensitive);
    }

    private function normalize_scalar_filter_value(string $field, string $type, mixed $value, bool $case_insensitive): mixed {
        if (in_array($type, ['int', 'number'], true)) {
            if (!is_int($value) && !is_float($value) && !is_numeric($value)) {
                throw new \InvalidArgumentException("Field {$field} requires a numeric value.");
            }

            return (string) $value;
        }

        if ($type === 'float') {
            if (!is_int($value) && !is_float($value) && !is_numeric($value)) {
                throw new \InvalidArgumentException("Field {$field} requires a numeric value.");
            }

            return (string) (float) $value;
        }

        $normalized = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        return $case_insensitive ? strtolower($normalized) : $normalized;
    }
}