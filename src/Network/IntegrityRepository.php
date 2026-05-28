<?php
namespace MYVH\Network;

if (!defined('ABSPATH')) {
    exit;
}

class IntegrityRepository {
    private string $runs_table;
    private string $findings_table;
    private string $status_table;

    public function __construct() {
        global $wpdb;

        $this->runs_table = $wpdb->base_prefix . 'myvh_integrity_runs';
        $this->findings_table = $wpdb->base_prefix . 'myvh_integrity_findings';
        $this->status_table = $wpdb->base_prefix . 'myvh_integrity_site_status';
    }

    public function get_active_run(): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            "SELECT * FROM {$this->runs_table} WHERE status IN ('queued', 'running') ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function create_run(?int $target_blog_id, int $queued_by_user_id, int $total_sites): int {
        global $wpdb;

        $created_at = current_time('mysql');

        $wpdb->insert(
            $this->runs_table,
            [
                'target_blog_id' => $target_blog_id,
                'status' => 'queued',
                'queued_by_user_id' => $queued_by_user_id,
                'total_sites' => max(0, $total_sites),
                'processed_sites' => 0,
                'error_sites' => 0,
                'warning_sites' => 0,
                'summary' => '',
                'created_at' => $created_at,
                'updated_at' => $created_at,
            ],
            ['%d', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public function mark_run_running(int $run_id): void {
        global $wpdb;

        $wpdb->update(
            $this->runs_table,
            [
                'status' => 'running',
                'started_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $run_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    public function increment_progress(int $run_id, bool $has_errors, bool $has_warnings): void {
        global $wpdb;

        $run_id = max(0, $run_id);
        if ($run_id <= 0) {
            return;
        }

        $error_increment = $has_errors ? 1 : 0;
        $warning_increment = $has_warnings ? 1 : 0;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->runs_table}
                 SET processed_sites = processed_sites + 1,
                     error_sites = error_sites + %d,
                     warning_sites = warning_sites + %d,
                     updated_at = %s
                 WHERE id = %d",
                $error_increment,
                $warning_increment,
                current_time('mysql'),
                $run_id
            )
        );
    }

    public function complete_run(int $run_id, string $status, string $summary): void {
        global $wpdb;

        $wpdb->update(
            $this->runs_table,
            [
                'status' => $status,
                'summary' => $summary,
                'completed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $run_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     */
    public function add_findings(int $run_id, int $blog_id, array $findings): void {
        global $wpdb;

        if ($run_id <= 0 || $blog_id <= 0 || empty($findings)) {
            return;
        }

        foreach ($findings as $finding) {
            $details = '';
            if (isset($finding['details']) && is_array($finding['details'])) {
                $encoded = wp_json_encode($finding['details']);
                $details = is_string($encoded) ? $encoded : '';
            }

            $wpdb->insert(
                $this->findings_table,
                [
                    'run_id' => $run_id,
                    'blog_id' => $blog_id,
                    'check_key' => (string) ($finding['check_key'] ?? 'unknown'),
                    'severity' => (string) ($finding['severity'] ?? 'info'),
                    'message' => (string) ($finding['message'] ?? ''),
                    'details' => $details,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
            );
        }
    }

    public function upsert_site_status(int $blog_id, int $run_id, string $run_status, int $error_count, int $warning_count, string $summary): void {
        global $wpdb;

        if ($blog_id <= 0 || $run_id <= 0) {
            return;
        }

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$this->status_table}
                (blog_id, last_run_id, last_status, last_run_at, error_count, warning_count, summary, updated_at)
                VALUES (%d, %d, %s, %s, %d, %d, %s, %s)
                ON DUPLICATE KEY UPDATE
                    last_run_id = VALUES(last_run_id),
                    last_status = VALUES(last_status),
                    last_run_at = VALUES(last_run_at),
                    error_count = VALUES(error_count),
                    warning_count = VALUES(warning_count),
                    summary = VALUES(summary),
                    updated_at = VALUES(updated_at)",
                $blog_id,
                $run_id,
                $run_status,
                current_time('mysql'),
                max(0, $error_count),
                max(0, $warning_count),
                $summary,
                current_time('mysql')
            )
        );
    }

    /**
     * @param array<int, int> $blog_ids
     * @return array<int, array<string, mixed>>
     */
    public function get_site_status_map(array $blog_ids): array {
        global $wpdb;

        $blog_ids = array_values(array_filter(array_map('intval', $blog_ids), static fn(int $id): bool => $id > 0));

        if (empty($blog_ids)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($blog_ids), '%d'));
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->status_table} WHERE blog_id IN ({$placeholders})",
            ...$blog_ids
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $id = (int) ($row['blog_id'] ?? 0);
            if ($id > 0) {
                $map[$id] = $row;
            }
        }

        return $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_recent_runs(int $limit = 20): array {
        global $wpdb;

        $limit = max(1, min(200, $limit));

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->runs_table} ORDER BY id DESC LIMIT %d",
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function get_run(int $run_id): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->runs_table} WHERE id = %d", $run_id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_run_findings(int $run_id, int $limit = 500): array {
        global $wpdb;

        $limit = max(1, min(2000, $limit));

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->findings_table} WHERE run_id = %d ORDER BY id ASC LIMIT %d",
            $run_id,
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}
