<?php

declare(strict_types=1);

namespace MYVH\Reports;

use wpdb;

final class ReportEngine {
    public function __construct(
        private ReportQueryBuilder $query_builder,
        private wpdb $wpdb,
        private ReportPermissionService $permission_service
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function run(Report $report, array $options = []): array {
        $current_user_id = isset($options['user_id']) ? (int) $options['user_id'] : (function_exists('get_current_user_id') ? get_current_user_id() : 0);
        $this->permission_service->assertCanRun($report, $current_user_id);

        $definition = json_decode($report->get_query_json(), true);
        if (!is_array($definition)) {
            throw new \InvalidArgumentException('Invalid query_json definition.');
        }

        if (isset($options['limit']) && (int) $options['limit'] > 0) {
            $definition['limit'] = (int) $options['limit'];
        }

        if (isset($options['offset']) && (int) $options['offset'] >= 0) {
            $definition['offset'] = (int) $options['offset'];
        }

        $schema = $this->query_builder->get_schema_for_source($report->get_data_source());
        $report_definition = ReportDefinition::fromArray($definition, $schema);
        $compiled = $this->query_builder->build($definition, $report->get_data_source(), $this->wpdb->prefix);
        $sql = $compiled['sql'];
        $params = $compiled['params'];

        $cache_key = 'myvh_report_' . md5($sql . serialize($params));
        $rows = [];
        $cached_rows = function_exists('get_transient') ? get_transient($cache_key) : false;

        if (is_array($cached_rows)) {
            $rows = $cached_rows;
        } else {
            $prepared_sql = $params !== [] ? $this->wpdb->prepare($sql, ...$params) : $sql;
            $result_rows = $this->wpdb->get_results($prepared_sql, ARRAY_A);
            $rows = is_array($result_rows) ? $result_rows : [];

            if (function_exists('set_transient')) {
                set_transient($cache_key, $rows, 300);
            }
        }

        $payload = [
            'rows' => $rows,
            'meta' => [
                'limit' => $report_definition->getLimit(),
                'offset' => $report_definition->getOffset(),
                'count' => count($rows),
            ],
        ];

        if (!empty($options['debug']) && $this->is_debug_allowed()) {
            $payload['sql'] = $sql;
            $payload['params'] = $params;
        }

        return $payload;
    }

    private function is_debug_allowed(): bool {
        return (defined('WP_DEBUG') && WP_DEBUG) && (function_exists('current_user_can') ? current_user_can('manage_options') : false);
    }
}
