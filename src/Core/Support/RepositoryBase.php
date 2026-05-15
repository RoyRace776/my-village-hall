<?php
namespace MYVH\Core\Support;

use MYVH\Audit\AuditTrail;

class RepositoryBase {
    /** @var \wpdb */
    protected $wpdb;
    /** @var string */
    protected $table_name;

    public function __construct($my_wpdb = null) {
        global $wpdb;
        $this->wpdb = $my_wpdb ?: $wpdb;
        $this->table_name = $this->resolve_table_name();
    }

    // --- Transaction helpers ---
    public function begin(): void { $this->wpdb->query('START TRANSACTION'); }
    public function commit(): void { $this->wpdb->query('COMMIT'); }
    public function rollback(): void { $this->wpdb->query('ROLLBACK'); }

    // --- CRUD ---
    public function create($data): int|false {
        $result = $this->wpdb->insert($this->table_name, $data, $this->get_format($data));
        if ($result) {
            $data['Id'] = (int) $this->wpdb->insert_id;
        }
        $this->audit('create', $data);
        return $result ? $this->wpdb->insert_id : false;
    }

    public function get_by_id($id): ?array {
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE Id = %d", $id);
        return $this->wpdb->get_row($sql, ARRAY_A);
    }

    public function get_all($args = []): array {
        $sql = "SELECT * FROM {$this->table_name}";
        if (!empty($args['orderby'])) {
            $orderby = $this->sanitize_identifier($args['orderby']);
            $order   = $this->normalize_order($args['order'] ?? 'ASC');
            if ($orderby !== '') {
                $sql .= " ORDER BY {$orderby} {$order}";
            }
        }
        if (!empty($args['limit'])) {
            $sql .= " LIMIT " . \intval($args['limit']);
            if (!empty($args['offset'])) {
                $sql .= " OFFSET " . \intval($args['offset']);
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
                $safe_col = $this->sanitize_identifier($col);
                if ($safe_col === '') {
                    continue; // skip any column key that doesn't pass validation
                }
                $clauses[] = $this->wpdb->prepare("{$safe_col} = %s", $val);
            }
            if (!empty($clauses)) {
                $sql .= " WHERE " . implode(' AND ', $clauses);
            }
        }
        if ($order !== '') {
            $order = $this->sanitize_identifier($order);
            if ($order !== '') {
                $sql .= " ORDER BY {$order}";
            }
        }
        if ($limit) $sql .= " LIMIT " . \intval($limit);
        if ($offset) $sql .= " OFFSET " . \intval($offset);
        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Validate a SQL identifier (column or table-qualified column).
     * Accepts bare column names (e.g. `Name`) and table-qualified names (e.g. `b.StartDate`).
     * Rejects anything containing characters outside [A-Za-z0-9_.] to prevent injection.
     *
     * @param string $identifier
     * @return string The validated identifier, or empty string if invalid.
     */
    protected function sanitize_identifier(string $identifier): string {
        $identifier = trim($identifier);
        if ($identifier === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $identifier)) {
            return '';
        }
        return $identifier;
    }

    /**
     * Normalise a sort direction to either 'ASC' or 'DESC'.
     *
     * @param mixed $order
     * @return string
     */
    protected function normalize_order($order): string {
        return 'DESC' === strtoupper((string) $order) ? 'DESC' : 'ASC';
    }

    // --- Table name resolution ---
    protected function resolve_table_name($table_name = null): string {

        if ($table_name) {
            return $this->wpdb->prefix . sanitize_key($table_name);
        }

        // Default: 'myvh_' + snake_case(class name w/o suffix)
        $class = get_class($this);
        $short = strtolower(preg_replace('/^|_Repository$/', '', $class));
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
        AuditTrail::record_repository_event($this, (string) $action, (array) $data, is_array($where) ? $where : null);
    }

    // --- Pagination ---
    public function paginate($page = 1, $per_page = 20, $where = []): array {
        $offset = ($page - 1) * $per_page;
        return $this->find($where, '', $per_page, $offset);
    }

}