<?php

declare(strict_types=1);

namespace MYVH\Reports\Http;

use MYVH\Reports\ReportPermissionService;
use MYVH\Reports\ReportQueryBuilder;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

final class SchemaReportEndpoint {
    private const REST_NAMESPACE = 'myvh/v1';
    private const REST_ROUTE = '/reports/schema';

    public function __construct(
        private ReportQueryBuilder $report_query_builder,
        private ReportPermissionService $permission_service
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
    }

    public function can_access(): bool {
        return is_user_logged_in();
    }

    public function handle(WP_REST_Request $request): WP_REST_Response {
        $current_user_id = get_current_user_id();
        $source = sanitize_key((string) $request->get_param('source'));
        $permissions = $this->permission_service->api_permissions($current_user_id);
        $allowed_sources = $permissions['allowedDataSources'] ?? [];
        $schema = $this->report_query_builder->get_schema();

        if (!is_array($allowed_sources)) {
            $allowed_sources = [];
        }

        if ($source !== '') {
            if (!in_array($source, $allowed_sources, true)) {
                return new WP_REST_Response([
                    'message' => __('Data source is not available for your account.', 'my-village-hall'),
                ], 403);
            }

            try {
                $schema = $this->report_query_builder->get_schema_for_source($source);
            } catch (\Throwable $throwable) {
                return new WP_REST_Response([
                    'message' => __('Unknown report source.', 'my-village-hall'),
                ], 400);
            }

            return new WP_REST_Response($schema, 200);
        }

        $sources = [];
        foreach ($allowed_sources as $allowed_source) {
            if (!is_string($allowed_source) || $allowed_source === '') {
                continue;
            }

            try {
                $sources[] = $this->report_query_builder->get_schema_for_source($allowed_source);
            } catch (\Throwable $throwable) {
                continue;
            }
        }

        return new WP_REST_Response([
            'sources' => $sources,
            'operators' => $schema['operators'] ?? [],
            'aggregateFunctions' => $schema['aggregateFunctions'] ?? [],
            'sortDirections' => $schema['sortDirections'] ?? ['ASC', 'DESC'],
            'maxLimit' => $schema['maxLimit'] ?? 1000,
        ], 200);
    }
}
