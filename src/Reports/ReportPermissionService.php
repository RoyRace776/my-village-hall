<?php

declare(strict_types=1);

namespace MYVH\Reports;

use MYVH\Portal\ClientAdminService;

if (!defined('ABSPATH')) {
    exit;
}

final class ReportPermissionService {
    public function __construct(
        private ClientAdminService $client_admin_service,
        private ReportQueryBuilder $report_query_builder
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function permissions_for_mode(int $user_id, string $mode): array {
        $is_privileged = $this->is_privileged_user($user_id);
        $mode = $mode === 'admin' ? 'admin' : 'portal';

        $allowed_sources = $is_privileged
            ? $this->report_query_builder->get_data_sources()
            : ['bookings'];

        if ($mode === 'admin' && !$is_privileged) {
            $allowed_sources = [];
        }

        return [
            'canCreateReports' => $is_privileged,
            'canDeleteReports' => $is_privileged,
            'canEditSystemReports' => $mode === 'admin' && $is_privileged,
            'allowedDataSources' => $allowed_sources,
            'canExportCSV' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function api_permissions(int $user_id): array {
        $is_privileged = $this->is_privileged_user($user_id);

        return [
            'isPrivilegedUser' => $is_privileged,
            'canCreateReports' => $is_privileged,
            'canDeleteReports' => $is_privileged,
            'canEditSystemReports' => $is_privileged,
            'allowedDataSources' => $is_privileged
                ? $this->report_query_builder->get_data_sources()
                : ['bookings'],
            'canExportCSV' => true,
        ];
    }

    public function user_can_access_report(Report $report, int $user_id): bool {
        $permissions = $this->api_permissions($user_id);
        $allowed_sources = $permissions['allowedDataSources'];

        if (!is_array($allowed_sources) || !in_array($report->get_data_source(), $allowed_sources, true)) {
            return false;
        }

        if (!empty($permissions['isPrivilegedUser'])) {
            return true;
        }

        if ($report->is_system()) {
            return true;
        }

        return $report->get_created_by() === $user_id;
    }

    public function assertCanRun(Report $report, int $user_id): void {
        if (!$this->user_can_access_report($report, $user_id)) {
            throw new \RuntimeException('You are not allowed to access this report.');
        }
    }

    public function user_can_use_data_source(int $user_id, string $source): bool {
        $permissions = $this->api_permissions($user_id);
        $allowed_sources = $permissions['allowedDataSources'];

        return is_array($allowed_sources) && in_array($source, $allowed_sources, true);
    }

    public function is_privileged_user(int $user_id): bool {
        if ($user_id <= 0) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        return $this->client_admin_service->can_administer_blog($user_id, get_current_blog_id());
    }
}
