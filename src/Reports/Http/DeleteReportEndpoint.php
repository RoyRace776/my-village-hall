<?php

declare(strict_types=1);

namespace MYVH\Reports\Http;

use MYVH\Reports\Report;
use MYVH\Reports\ReportPermissionService;
use MYVH\Reports\ReportRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

final class DeleteReportEndpoint {
    private const REST_NAMESPACE = 'myvh/v1';

    public function __construct(
        private ReportRepository $report_repository,
        private ReportPermissionService $permission_service
    ) {
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_route']);
    }

    public function register_route(): void {
        register_rest_route(self::REST_NAMESPACE, '/reports/(?P<report_id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'handle'],
            'permission_callback' => [$this, 'can_access'],
        ]);
    }

    public function can_access(): bool {
        return is_user_logged_in();
    }

    public function handle(WP_REST_Request $request): WP_REST_Response {
        $report_id = (int) $request->get_param('report_id');
        if ($report_id <= 0) {
            return new WP_REST_Response([
                'message' => __('Only database-backed reports can be deleted.', 'my-village-hall'),
            ], 400);
        }

        $current_user_id = get_current_user_id();
        $permissions = $this->permission_service->api_permissions($current_user_id);

        if (empty($permissions['canDeleteReports'])) {
            return new WP_REST_Response([
                'message' => __('You are not allowed to delete reports.', 'my-village-hall'),
            ], 403);
        }

        $report = $this->report_repository->find($report_id);
        if (!$report instanceof Report) {
            return new WP_REST_Response([
                'message' => __('Report not found.', 'my-village-hall'),
            ], 404);
        }

        if (!$this->permission_service->user_can_access_report($report, $current_user_id)) {
            return new WP_REST_Response([
                'message' => __('You are not allowed to delete this report.', 'my-village-hall'),
            ], 403);
        }

        try {
            $deleted = $this->report_repository->delete($report_id);
        } catch (\Throwable $throwable) {
            return new WP_REST_Response([
                'message' => __('Failed to delete report.', 'my-village-hall'),
            ], 400);
        }

        return new WP_REST_Response([
            'deleted' => $deleted,
            'reportId' => $report_id,
        ], 200);
    }
}