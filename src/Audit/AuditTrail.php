<?php
namespace MYVH\Audit;

use MYVH\Portal\ClientAdminService;

if (!defined('ABSPATH')) {
    exit;
}

class AuditTrail {
    private const ACTION_CREATE = 'create';
    private const ACTION_DELETE = 'delete';

    private const ENTITY_MAP = [
        'BookingRepository' => 'booking',
        'CustomerRepository' => 'customer',
        'OrganisationRepository' => 'organisation',
        'OrganisationTypeRepository' => 'organisation_type',
        'VenueRepository' => 'venue',
        'RoomRepository' => 'room',
        'RoomRateRepository' => 'room_rate',
        'AddonRepository' => 'addon',
        'InvoiceRepository' => 'invoice',
        'PaymentRepository' => 'payment',
    ];

    public static function is_enabled(): bool {
        return (bool) myvh_setting('admin.enable_auditing', false);
    }

    public static function can_reset(): bool {
        return current_user_can('manage_options');
    }

    public static function can_view_dashboard(): bool {
        return current_user_can('manage_options');
    }

    public static function can_view_portal(): bool {
        $user_id = get_current_user_id();

        if ($user_id <= 0) {
            return false;
        }

        if (current_user_can('manage_myvh')) {
            return true;
        }

        $client_admin_service = new ClientAdminService();
        return $client_admin_service->can_administer_blog($user_id, get_current_blog_id());
    }

    public static function record_repository_event(object $repository, string $action, array $data = [], ?array $where = null): void {
        if (!self::is_enabled()) {
            return;
        }

        if (!in_array($action, [self::ACTION_CREATE, self::ACTION_DELETE], true)) {
            return;
        }

        $class_name = self::repository_short_name($repository);
        $entity_type = self::ENTITY_MAP[$class_name] ?? null;

        if (!$entity_type) {
            return;
        }

        $entity_id = null;
        if (isset($data['Id'])) {
            $entity_id = (int) $data['Id'];
        }

        if ($entity_id === null && is_array($where) && isset($where['Id'])) {
            $entity_id = (int) $where['Id'];
        }

        $summary = self::build_summary($data, $where);

        self::record($action, $entity_type, $entity_id, $summary);
    }

    public static function record(
        string $action,
        string $entity_type,
        ?int $entity_id = null,
        array $summary = [],
        ?string $origin = null,
        ?int $actor_user_id = null
    ): void {
        if (!self::is_enabled()) {
            return;
        }

        global $wpdb;

        $insert_data = [
            'Action' => sanitize_key($action),
            'EntityType' => sanitize_key($entity_type),
            'EntityId' => $entity_id,
            'ActorUserId' => $actor_user_id ?? self::detect_actor_user_id(),
            'Origin' => $origin ?? self::detect_origin(),
            'Summary' => wp_json_encode($summary),
            'CreatedAt' => current_time('mysql'),
        ];

        $wpdb->insert(
            self::table_name(),
            $insert_data,
            ['%s', '%s', '%d', '%d', '%s', '%s', '%s']
        );
    }

    public static function query(array $args = []): array {
        global $wpdb;

        $defaults = [
            'page' => 1,
            'per_page' => 50,
            'action' => '',
            'entity_type' => '',
            'origin' => '',
            'actor_user_id' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $page = max(1, (int) $args['page']);
        $per_page = max(1, min(200, (int) $args['per_page']));
        $offset = ($page - 1) * $per_page;

        $where_sql = ' WHERE 1=1 ';
        $params = [];

        if (!empty($args['action'])) {
            $where_sql .= ' AND Action = %s';
            $params[] = sanitize_key((string) $args['action']);
        }

        if (!empty($args['entity_type'])) {
            $where_sql .= ' AND EntityType = %s';
            $params[] = sanitize_key((string) $args['entity_type']);
        }

        if (!empty($args['origin'])) {
            $where_sql .= ' AND Origin = %s';
            $params[] = sanitize_key((string) $args['origin']);
        }

        if (!empty($args['actor_user_id'])) {
            $where_sql .= ' AND ActorUserId = %d';
            $params[] = (int) $args['actor_user_id'];
        }

        $count_sql = 'SELECT COUNT(*) FROM ' . self::table_name() . $where_sql;
        $total = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, $params) : $count_sql);

        $rows_sql = 'SELECT * FROM ' . self::table_name() . $where_sql . ' ORDER BY Id DESC LIMIT %d OFFSET %d';
        $rows_params = $params;
        $rows_params[] = $per_page;
        $rows_params[] = $offset;
        $rows = $wpdb->get_results($wpdb->prepare($rows_sql, $rows_params), ARRAY_A);

        foreach ($rows as &$row) {
            $row['SummaryArray'] = [];
            if (!empty($row['Summary'])) {
                $decoded = json_decode((string) $row['Summary'], true);
                if (is_array($decoded)) {
                    $row['SummaryArray'] = $decoded;
                }
            }
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => max(1, (int) ceil($total / $per_page)),
        ];
    }

    public static function reset(): bool {
        if (!self::can_reset()) {
            return false;
        }

        global $wpdb;
        return $wpdb->query('DELETE FROM ' . self::table_name()) !== false;
    }

    private static function repository_short_name(object $repository): string {
        $class_name = get_class($repository);
        $parts = explode('\\', $class_name);
        return (string) end($parts);
    }

    private static function build_summary(array $data, ?array $where): array {
        $summary = [];
        $allowed_fields = ['Id', 'Name', 'Status', 'Email', 'InvoiceNumber', 'Description'];

        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $data)) {
                $summary[$field] = $data[$field];
            }
        }

        if (!empty($where)) {
            $summary['Where'] = $where;
        }

        return $summary;
    }

    private static function detect_actor_user_id(): int {
        $user_id = (int) get_current_user_id();
        return $user_id > 0 ? $user_id : 0;
    }

    private static function detect_origin(): string {
        if (wp_doing_ajax()) {
            $action = sanitize_key($_REQUEST['action'] ?? '');
            if (strpos($action, 'myvh_portal_') === 0) {
                return 'portal';
            }
            return 'ajax';
        }

        if (is_admin()) {
            return 'dashboard';
        }

        return 'system';
    }

    private static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'myvh_audit_log';
    }
}