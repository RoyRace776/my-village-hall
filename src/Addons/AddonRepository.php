<?php
namespace MYVH\Addons;
use MYVH\Core\Support\RepositoryBase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use wpdb;

class AddonRepository extends RepositoryBase {

    private LoggerInterface $logger;

    /**
     * Constructor
     */
    public function __construct( wpdb $wpdb, ?LoggerInterface $logger = null ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_addons';
        $this->logger = $logger ?? new NullLogger();
    }

    // Custom methods preserved
    public function get_all_with_relations(bool $include_archived = false): array {
        $where = $include_archived ? '' : 'WHERE a.ArchivedAt IS NULL';

        $sql = "SELECT\n                    a.*,\n                    r.Name as RoomName\n                FROM {$this->table_name} a\n                LEFT JOIN {$this->wpdb->prefix}myvh_rooms r ON a.RoomId = r.Id\n                {$where}\n                ORDER BY a.DisplayOrder, a.Name";

        $results = $this->wpdb->get_results($sql);
        if ($results === null) {
            $this->logger->error('Addon repository query failed', [
                'method' => 'get_all_with_relations',
                'db_error' => (string) $this->wpdb->last_error,
            ]);
        }
        return $this->rows_to_arrays($results ?: []);
    }

    public function get_by_room($room_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE (RoomId = %d OR RoomId IS NULL) AND IsActive = 1 AND ArchivedAt IS NULL ORDER BY DisplayOrder",
            $room_id
        );
        $results = $this->wpdb->get_results($sql);
        if ($results === null) {
            $this->logger->error('Addon repository query failed', [
                'method' => 'get_by_room',
                'room_id' => (int) $room_id,
                'db_error' => (string) $this->wpdb->last_error,
            ]);
        }
        return $this->rows_to_arrays($results ?: []);
    }

    public function get_active_by_id(int $id): ?array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE Id = %d AND ArchivedAt IS NULL",
            $id
        );

        $result = $this->wpdb->get_row($sql);
        if ($result === null && !empty($this->wpdb->last_error)) {
            $this->logger->error('Addon repository query failed', [
                'method' => 'get_active_by_id',
                'addon_id' => $id,
                'db_error' => (string) $this->wpdb->last_error,
            ]);
        }

        if ($result === null) {
            return null;
        }

        return (array) $result;
    }

    public function get_all_active(array $args = []): array {
        $sql = "SELECT * FROM {$this->table_name} WHERE ArchivedAt IS NULL";

        if (!empty($args['orderby'])) {
            $orderby = $this->sanitize_identifier($args['orderby']);
            $order   = $this->normalize_order($args['order'] ?? 'ASC');
            $sql .= $orderby !== '' ? " ORDER BY {$orderby} {$order}" : ' ORDER BY DisplayOrder, Name';
        } else {
            $sql .= ' ORDER BY DisplayOrder, Name';
        }

        if (!empty($args['limit'])) {
            $sql .= ' LIMIT ' . \intval($args['limit']);
            if (!empty($args['offset'])) {
                $sql .= ' OFFSET ' . \intval($args['offset']);
            }
        }

        $results = $this->wpdb->get_results($sql);
        if ($results === null) {
            $this->logger->error('Addon repository query failed', [
                'method' => 'get_all_active',
                'db_error' => (string) $this->wpdb->last_error,
            ]);
            return [];
        }

        return $this->rows_to_arrays($results);
    }

    public function archive_by_id(int $id): bool {
        $result = $this->wpdb->update(
            $this->table_name,
            [
                'ArchivedAt' => current_time('mysql'),
                'IsActive' => 0,
            ],
            ['Id' => $id],
            ['%s', '%d'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * @param array<int,object|array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function rows_to_arrays(array $rows): array {
        return array_map(static function ($row): array {
            return (array) $row;
        }, $rows);
    }
}