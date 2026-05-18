<?php

namespace MYVH\Network;

class SiteProvisioningRepository {
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->base_prefix . 'myvh_site_provisioning';
    }

    public function create(array $data): int {
        global $wpdb;

        $wpdb->insert($this->table, [
            'token' => $data['token'],
            'subdomain' => $data['subdomain'],
            'site_name' => $data['site_name'],
            'admin_email' => $data['admin_email'],
            'admin_first_name' => $data['admin_first_name'],
            'admin_last_name' => $data['admin_last_name'],
            'admin_password' => $data['admin_password'], // TODO: hash password
            'status' => 'pending',
            'logo_url' => $data['logo_url'] ?? '',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        return (int) $wpdb->insert_id;
    }

    public function find_by_token(string $token): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE token = %s", $token),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function update_status(int $id, string $status, array $extra = []): void {
        global $wpdb;

        $data = array_merge([
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ], $extra);

        $wpdb->update($this->table, $data, ['id' => $id]);
    }

    public function delete(int $id): void {
        global $wpdb;
        $wpdb->delete($this->table, ['id' => $id]);
    }

    public function get_by_site_id(int $site_id): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE blog_id = %d", $site_id),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function get_all(int $offset = 0, int $limit = 50): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return $rows ?: [];
    }

    public function count_all(): int {
        global $wpdb;

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");

        return (int) $count;
    }

    public function get_by_id(int $id): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ?: null;
    }
}