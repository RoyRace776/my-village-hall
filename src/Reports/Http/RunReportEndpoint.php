<?php

declare(strict_types=1);

namespace MYVH\Reports\Http;

use MYVH\Reports\Report;
use MYVH\Reports\CsvExporter;
use MYVH\Reports\ReportEngine;
use MYVH\Reports\ReportPermissionService;
use MYVH\Reports\ReportRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

final class RunReportEndpoint {
    private const REST_NAMESPACE = 'myvh/v1';
    private const REST_ROUTE = '/reports/run';

    public function __construct(
        private ReportRepository $report_repository,
        private ReportEngine $report_engine,
        private ReportPermissionService $permission_service,
        private CsvExporter $csv_exporter
    ) {
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_route']);
    }

    public function register_route(): void {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle'],
            'permission_callback' => [$this, 'can_access'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/reports/(?P<report_id>\d+)/run', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle'],
            'permission_callback' => [$this, 'can_access'],
        ]);
    }

    public function can_access(): bool {
        return is_user_logged_in();
    }

    public function handle(WP_REST_Request $request): WP_REST_Response {
        $report_id = (int) $request->get_param('report_id');
        if ($report_id === 0) {
            return new WP_REST_Response([
                'message' => __('report_id is required', 'my-village-hall'),
            ], 400);
        }

        $report = $this->report_repository->find($report_id);
        if (!$report instanceof Report) {
            return new WP_REST_Response([
                'message' => __('Report not found', 'my-village-hall'),
            ], 404);
        }

        $current_user_id = get_current_user_id();
        if (!$this->permission_service->user_can_access_report($report, $current_user_id)) {
            return new WP_REST_Response([
                'message' => __('You are not allowed to access this report.', 'my-village-hall'),
            ], 403);
        }

        $limit = (int) $request->get_param('limit');
        $offset = (int) $request->get_param('offset');
        $debug = (string) $request->get_param('debug') === '1';
        $format = sanitize_key((string) $request->get_param('format'));

        try {
            $result = $this->report_engine->run($report, [
                'user_id' => $current_user_id,
                'limit' => $limit > 0 ? $limit : null,
                'offset' => $offset >= 0 ? $offset : null,
                'debug' => $debug,
            ]);
        } catch (\Throwable $throwable) {
            if ($throwable instanceof \RuntimeException && stripos($throwable->getMessage(), 'not allowed') !== false) {
                return new WP_REST_Response([
                    'message' => __('You are not allowed to access this report.', 'my-village-hall'),
                ], 403);
            }

            return new WP_REST_Response([
                'message' => __('Failed to run report definition.', 'my-village-hall'),
            ], 400);
        }

        $rows = isset($result['rows']) && is_array($result['rows']) ? $result['rows'] : [];

        if ($format === 'csv') {
            $csv = $this->csv_exporter->output($rows);
            $response = new WP_REST_Response($csv, 200);
            $response->set_headers([
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => sprintf('attachment; filename="report-%d.csv"', $report_id),
            ]);

            return $response;
        }

        if ($debug && isset($result['sql'], $result['params'])) {
            return new WP_REST_Response([
                'sql' => (string) $result['sql'],
                'params' => is_array($result['params']) ? $result['params'] : [],
                'rows' => $rows,
                'meta' => $result['meta'] ?? [],
            ], 200);
        }

        return new WP_REST_Response([
            'data' => $rows,
            'meta' => $result['meta'] ?? [],
        ], 200);
    }
}
