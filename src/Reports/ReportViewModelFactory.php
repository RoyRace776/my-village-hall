<?php

declare(strict_types=1);

namespace MYVH\Reports;

use MYVH\Settings\GeneralSettings;

if (!defined('ABSPATH')) {
    exit;
}

final class ReportViewModelFactory {
    public function __construct(
        private ReportRepository $report_repository,
        private ReportQueryBuilder $report_query_builder,
        private ReportPermissionService $report_permission_service
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build_for_mode(int $user_id, string $mode): array {
        $mode = $mode === 'admin' ? 'admin' : 'portal';
        $ui_permissions = $this->report_permission_service->permissions_for_mode($user_id, $mode);
        $api_permissions = $this->report_permission_service->api_permissions($user_id);
        $is_privileged_user = !empty($api_permissions['isPrivilegedUser']);

        $reports = array_values(array_filter(
            $this->report_repository->getAccessibleReports($user_id, $is_privileged_user),
            function (Report $report) use ($ui_permissions): bool {
                $allowed = $ui_permissions['allowedDataSources'] ?? [];
                return is_array($allowed) && in_array($report->get_data_source(), $allowed, true);
            }
        ));

        usort($reports, static function (Report $left, Report $right): int {
            return strcasecmp($left->get_name(), $right->get_name());
        });

        $allowed_sources = $ui_permissions['allowedDataSources'] ?? [];
        $schemas = [];

        if (is_array($allowed_sources)) {
            foreach ($allowed_sources as $source) {
                if (!is_string($source) || $source === '') {
                    continue;
                }

                try {
                    $schemas[] = $this->report_query_builder->get_schema_for_source($source);
                } catch (\Throwable $throwable) {
                    continue;
                }
            }
        }

        $report_list = array_map(static function (Report $report): array {
            return [
                'id' => $report->get_id(),
                'name' => $report->get_name(),
                'description' => $report->get_description(),
                'type' => $report->get_type(),
                'data_source' => $report->get_data_source(),
                'query_json' => $report->get_query_json(),
                'created_by' => $report->get_created_by(),
                'created_at' => $report->get_created_at(),
            ];
        }, $reports);

        return [
            'context' => [
                'mode' => $mode,
                'userId' => $user_id,
                'permissions' => $ui_permissions,
            ],
            'bootstrap' => [
                'reports' => $report_list,
                'schema' => [
                    'sources' => $schemas,
                ],
                'endpoints' => [
                    'run' => rest_url('myvh/v1/reports/run'),
                    'save' => rest_url('myvh/v1/reports'),
                    'delete' => rest_url('myvh/v1/reports'),
                    'schema' => rest_url('myvh/v1/reports/schema'),
                ],
                'restNonce' => wp_create_nonce('wp_rest'),
                'pagination' => [
                    'pageLength' => $this->get_report_page_length(),
                ],
            ],
        ];
    }

    private function get_report_page_length(): int {
        $general_settings = new GeneralSettings();
        $configured = (int) $general_settings->get('report_page_length');

        return $configured > 0 ? $configured : 25;
    }
}
