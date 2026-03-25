<?php

class MYVH_Repository_Base {
    /** @var wpdb */
    protected $wpdb;
    /** @var string */
    protected $table_name;

    public function __construct($my_wpdb = null) {
        global $wpdb;
        $this->wpdb = $wpdb ?: $wpdb;
        $this->table_name = $this->resolve_table_name();
    }

    // --- Transaction helpers ---
    public function begin(): void { $this->wpdb->query('START TRANSACTION'); }
    public function commit(): void { $this->wpdb->query('COMMIT'); }
    public function rollback(): void { $this->wpdb->query('ROLLBACK'); }

    // --- CRUD ---
    public function create($data): int|false {
        $result = $this->wpdb->insert($this->table_name, $data, $this->get_format($data));
        $this->audit('create', $data);
        return $result ? $this->wpdb->insert_id : false;
    }

    public function get_by_id($id): ?array {
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE Id = %d", $id);
        return $this->wpdb->get_row($sql, ARRAY_A);
    }

    public function get_all($args = []): array {
        $sql = "SELECT * FROM {$this->table_name}";
        // Add order, limit, offset if present
        if (!empty($args['orderby'])) {
            $sql .= " ORDER BY " . esc_sql($args['orderby']) . ' ' . esc_sql($args['order'] ?? 'ASC');
        }
        if (!empty($args['limit'])) {
            $sql .= " LIMIT " . intval($args['limit']);
            if (!empty($args['offset'])) {
                $sql .= " OFFSET " . intval($args['offset']);
            }
        }
        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function update($data, $where): bool {
        $result = $this->wpdb->update($this->table_name, $data, $where, $this->get_format($data));
        $this->audit('update', $data, $where);
        return $result !== false;
    }

    public function delete_by_id($id): bool {
        $result = $this->wpdb->delete($this->table_name, ['Id' => $id], ['%d']);
        $this->audit('delete', ['Id' => $id]);
        return $result !== false;
    }

    //Just for compatibility with existing code that calls delete() instead of delete_by_id()
    public function delete($id): bool {
        return $this->delete_by_id($id);
    }

    // --- Query helpers ---
    public function find($where = [], $order = '', $limit = null, $offset = null): array {
        $sql = "SELECT * FROM {$this->table_name}";
        if ($where) {
            $clauses = [];
            foreach ($where as $col => $val) {
                $clauses[] = $this->wpdb->prepare("`$col` = %s", $val);
            }
            $sql .= " WHERE " . implode(' AND ', $clauses);
        }
        if ($order) $sql .= " ORDER BY $order";
        if ($limit) $sql .= " LIMIT " . intval($limit);
        if ($offset) $sql .= " OFFSET " . intval($offset);
        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    // --- Table name resolution ---
    protected function resolve_table_name($table_name = null): string {

        if ($table_name) {
            return $this->wpdb->prefix . sanitize_key($table_name);
        }

        // Default: 'myvh_' + snake_case(class name w/o suffix)
        $class = get_class($this);
        $short = strtolower(preg_replace('/^MYVH_|_Repository$/', '', $class));
        global $wpdb;
        return $wpdb->prefix . 'myvh_' . $short . 's';
    }

    // --- Format helper ---
    protected function get_format($data): array {
        $formats = [];
        foreach ($data as $v) {
            $formats[] = is_int($v) ? '%d' : (is_float($v) ? '%f' : '%s');
        }
        return $formats;
    }

    // --- Soft delete ---
    public function soft_delete_by_id($id): bool {
        return $this->update(['IsDeleted' => 1], ['Id' => $id]);
    }
    public function restore_by_id($id): bool {
        return $this->update(['IsDeleted' => 0], ['Id' => $id]);
    }
    public function with_deleted(): array {
        $sql = "SELECT * FROM {$this->table_name}";
        return $this->wpdb->get_results($sql, ARRAY_A);
    }
    public function only_deleted(): array {
        $sql = "SELECT * FROM {$this->table_name} WHERE IsDeleted = 1";
        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    // --- Audit logging (stub) ---
    protected function audit($action, $data, $where = null): void {
        // Implement audit log logic here (e.g., write to log table)
    }

    // --- Pagination ---
    public function paginate($page = 1, $per_page = 20, $where = []): array {
        $offset = ($page - 1) * $per_page;
        return $this->find($where, '', $per_page, $offset);
    }

    // --- Validation (stub) ---
    protected function validate($data): bool {
        // Implement schema validation logic here
        return true;
    }

    // --- Caching (stub) ---
    protected function cache_get($key): bool { return false; }
    protected function cache_set($key, $value, $ttl = 300): bool { return true; }

    // --- Batch/bulk operations (stub) ---
    public function bulk_insert($rows): void {
        foreach ($rows as $row) $this->create($row);
    }
    public function bulk_update($rows, $key = 'Id'): void {
        foreach ($rows as $row) if (isset($row[$key])) $this->update($row, [$key => $row[$key]]);
    }
    public function bulk_delete($ids): void {
        foreach ($ids as $id) $this->delete_by_id($id);
    }
}