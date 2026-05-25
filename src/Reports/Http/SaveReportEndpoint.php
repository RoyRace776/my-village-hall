<?php

declare(strict_types=1);

namespace MYVH\Reports\Http;

use MYVH\Reports\Report;
use MYVH\Reports\ReportDefinition;
use MYVH\Reports\ReportPermissionService;
use MYVH\Reports\ReportRepository;
use MYVH\Reports\ReportQueryBuilder;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

final class SaveReportEndpoint {
    private const REST_NAMESPACE = 'myvh/v1';
    private const REST_ROUTE = '/reports';

    public function __construct(
        private ReportRepository $report_repository,
        private ReportPermissionService $permission_service,
        private ReportQueryBuilder $report_query_builder
    ) {
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_route']);
    }

    public function register_route(): void {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle'],
            'permission_callback' => [$this, 'can_access'],
        ]);
    }

    public function can_access(): bool {
        return is_user_logged_in();
    }

    public function handle(WP_REST_Request $request): WP_REST_Response {
        $current_user_id = get_current_user_id();
        $permissions = $this->permission_service->api_permissions($current_user_id);

        if (empty($permissions['canCreateReports'])) {
            return new WP_REST_Response([
                'message' => __('You are not allowed to create reports.', 'my-village-hall'),
            ], 403);
        }

        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        $report_id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $name = sanitize_text_field((string) ($payload['name'] ?? ''));
        $description = sanitize_textarea_field((string) ($payload['description'] ?? ''));
        $type = sanitize_key((string) ($payload['type'] ?? Report::TYPE_USER));
        $data_source = sanitize_key((string) ($payload['data_source'] ?? ''));
        $definition = $payload['definition'] ?? null;

        if ($report_id < 0) {
            $report_id = 0;
            $type = Report::TYPE_USER;
        }

        if ($name === '') {
            return new WP_REST_Response([
                'message' => __('Report name is required.', 'my-village-hall'),
            ], 400);
        }

        if ($this->report_repository->nameExists($name, $report_id)) {
            return new WP_REST_Response([
                'message' => __('Report name must be unique.', 'my-village-hall'),
            ], 400);
        }

        if (!in_array($type, [Report::TYPE_SYSTEM, Report::TYPE_USER], true)) {
            $type = Report::TYPE_USER;
        }

        if ($type === Report::TYPE_SYSTEM && empty($permissions['canEditSystemReports'])) {
            return new WP_REST_Response([
                'message' => __('You are not allowed to save system reports.', 'my-village-hall'),
            ], 403);
        }

        if (!$this->permission_service->user_can_use_data_source($current_user_id, $data_source)) {
            return new WP_REST_Response([
                'message' => __('Data source is not available for your account.', 'my-village-hall'),
            ], 403);
        }

        $existing = null;
        if ($report_id > 0) {
            $existing = $this->report_repository->find($report_id);
            if (!$existing instanceof Report) {
                return new WP_REST_Response([
                    'message' => __('Report not found.', 'my-village-hall'),
                ], 404);
            }

            if (!$this->permission_service->user_can_access_report($existing, $current_user_id)) {
                return new WP_REST_Response([
                    'message' => __('You are not allowed to edit this report.', 'my-village-hall'),
                ], 403);
            }

            if ($existing->is_system() && empty($permissions['canEditSystemReports'])) {
                return new WP_REST_Response([
                    'message' => __('You are not allowed to edit system reports.', 'my-village-hall'),
                ], 403);
            }
        }

        if (!is_array($definition)) {
            return new WP_REST_Response([
                'message' => __('A definition object is required.', 'my-village-hall'),
            ], 400);
        }

        try {
            $definition = ReportDefinition::fromArray(
                $definition,
                $this->report_query_builder->get_schema_for_source($data_source)
            )->toArray();
        } catch (\Throwable $throwable) {
            return new WP_REST_Response([
                'message' => __('Invalid report definition.', 'my-village-hall'),
            ], 400);
        }

        $query_json = wp_json_encode($definition);
        if (!is_string($query_json) || $query_json === '') {
            return new WP_REST_Response([
                'message' => __('Unable to encode report definition.', 'my-village-hall'),
            ], 400);
        }

        $created_by = $type === Report::TYPE_SYSTEM
            ? null
            : ($existing instanceof Report ? $existing->get_created_by() : $current_user_id);

        $report = new Report(
            $report_id,
            $name,
            $description,
            $type,
            $data_source,
            $query_json,
            $created_by,
            $existing instanceof Report ? $existing->get_created_at() : gmdate('Y-m-d H:i:s')
        );

        try {
            $saved_id = $this->report_repository->save($report);
            $saved_report = $this->report_repository->find($saved_id);
        } catch (\Throwable $throwable) {
            return new WP_REST_Response([
                'message' => __('Failed to save report.', 'my-village-hall'),
            ], 400);
        }

        return new WP_REST_Response([
            'report' => $saved_report instanceof Report ? $saved_report->to_array() : [
                'id' => $saved_id,
            ],
        ], 200);
    }
}
