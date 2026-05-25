<?php

declare(strict_types=1);

namespace MYVH\Reports;

use wpdb;

/**
 * @method bool delete(int $id)
 * @method bool nameExists(string $name, int $excludeId = 0)
 */
final class ReportRepository {
    private string $table_name;

    public function __construct(private wpdb $wpdb) {
        $this->table_name = $wpdb->prefix . 'myvh_reports';
    }

    public function find(int $id): ?Report {
        $system_reports = $this->get_system_reports_indexed();
        if (isset($system_reports[$id])) {
            return $system_reports[$id];
        }

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, name, description, type, data_source, query_json, created_by, created_at
                 FROM {$this->table_name}
                 WHERE id = %d
                 LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @return array<int, Report>
     */
    public function getSystemReports(): array {
        $reports = $this->get_system_reports_indexed();

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, name, description, type, data_source, query_json, created_by, created_at
                 FROM {$this->table_name}
                 WHERE type = %s
                 ORDER BY created_at DESC",
                Report::TYPE_SYSTEM
            ),
            ARRAY_A
        );

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $report = $this->hydrate($row);
                $reports[$report->get_id()] = $report;
            }
        }

        return array_values($reports);
    }

    /**
     * @return array<int, Report>
     */
    public function getUserReports(int $userId): array {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, name, description, type, data_source, query_json, created_by, created_at
                 FROM {$this->table_name}
                 WHERE type = %s AND created_by = %d
                 ORDER BY created_at DESC",
                Report::TYPE_USER,
                $userId
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $reports = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $reports[] = $this->hydrate($row);
        }

        return $reports;
    }

    public function save(Report $report): int {
        $data = [
            'name' => $report->get_name(),
            'description' => $report->get_description(),
            'type' => $report->get_type(),
            'data_source' => $report->get_data_source(),
            'query_json' => $report->get_query_json(),
            'created_by' => $report->get_created_by(),
        ];

        $formats = ['%s', '%s', '%s', '%s', '%s', '%d'];

        if ($report->get_id() > 0) {
            $updated = $this->wpdb->update(
                $this->table_name,
                $data,
                ['id' => $report->get_id()],
                $formats,
                ['%d']
            );

            if ($updated === false) {
                throw new \RuntimeException('Failed to update report definition.');
            }

            return $report->get_id();
        }

        $inserted = $this->wpdb->insert($this->table_name, $data, $formats);

        if ($inserted === false) {
            throw new \RuntimeException('Failed to insert report definition.');
        }

        return (int) $this->wpdb->insert_id;
    }

    public function nameExists(string $name, int $excludeId = 0): bool {
        $normalized_name = trim($name);
        if ($normalized_name === '') {
            return false;
        }

        foreach ($this->get_system_reports_indexed() as $report) {
            if ($report->get_id() === $excludeId) {
                continue;
            }

            if (strcasecmp($report->get_name(), $normalized_name) === 0) {
                return true;
            }
        }

        $query = "SELECT id
            FROM {$this->table_name}
            WHERE LOWER(name) = LOWER(%s)";

        $params = [$normalized_name];

        if ($excludeId > 0) {
            $query .= ' AND id != %d';
            $params[] = $excludeId;
        }

        $query .= ' LIMIT 1';

        $row = $this->wpdb->get_var($this->wpdb->prepare($query, ...$params));

        return $row !== null;
    }

    public function delete(int $id): bool {
        $deleted = $this->wpdb->delete(
            $this->table_name,
            ['id' => $id],
            ['%d']
        );

        if ($deleted === false) {
            throw new \RuntimeException('Failed to delete report definition.');
        }

        return $deleted > 0;
    }

    /**
     * @return array<int, Report>
     */
    public function getAccessibleReports(int $userId, bool $isPrivilegedUser): array {
        if ($isPrivilegedUser) {
            $rows = $this->wpdb->get_results(
                "SELECT id, name, description, type, data_source, query_json, created_by, created_at
                 FROM {$this->table_name}
                 ORDER BY created_at DESC",
                ARRAY_A
            );

            $reports = [];
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $report = $this->hydrate($row);
                    $reports[$report->get_id()] = $report;
                }
            }

            foreach ($this->get_system_reports_indexed() as $id => $report) {
                $reports[$id] = $report;
            }

            return array_values($reports);
        }

        return array_merge(
            $this->getSystemReports(),
            $this->getUserReports($userId)
        );
    }

    /**
     * @return array<int, Report>
     */
    private function get_system_reports_indexed(): array {
        return SystemReportDefinitions::all();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Report {
        return new Report(
            (int) ($row['id'] ?? 0),
            (string) ($row['name'] ?? ''),
            (string) ($row['description'] ?? ''),
            (string) ($row['type'] ?? Report::TYPE_USER),
            (string) ($row['data_source'] ?? ''),
            (string) ($row['query_json'] ?? '{}'),
            isset($row['created_by']) ? (int) $row['created_by'] : null,
            (string) ($row['created_at'] ?? '')
        );
    }
}
