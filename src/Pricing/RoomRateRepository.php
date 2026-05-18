<?php
namespace MYVH\Pricing;

use MYVH\Core\Support\RepositoryBase;

if (!defined('ABSPATH')) exit;

class RoomRateRepository extends RepositoryBase{

    private string $day_table_name;
    private ?bool $has_day_table = null;

    public function __construct( \wpdb $wpdb ) {
        $this->wpdb  = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_room_rates';
        $this->day_table_name = $wpdb->prefix . 'myvh_room_rate_days';
    }

    private function day_table_exists(): bool {
        if ($this->has_day_table !== null) {
            return $this->has_day_table;
        }

        $table_name = $this->wpdb->esc_like($this->day_table_name);
        $result = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        $this->has_day_table = !empty($result);

        return $this->has_day_table;
    }

    /**
     * @param array<int, mixed> $days
     * @return array<int, int>
     */
    private function normalize_days(array $days): array {
        $normalized = [];
        foreach ($days as $raw_day) {
            $value = trim((string) $raw_day);
            if ($value === '' || !ctype_digit($value)) {
                continue;
            }

            $day = intval($value);
            if ($day >= 0 && $day <= 6) {
                $normalized[$day] = $day;
            }
        }

        ksort($normalized);
        return array_values($normalized);
    }

    /**
     * @param array<int, array<string, mixed>> $rates
     * @return array<int, array<string, mixed>>
     */
    public function hydrate_rates_with_days(array $rates): array {
        if (empty($rates)) {
            return $rates;
        }

        $rate_ids = [];
        foreach ($rates as $rate) {
            $id = intval($rate['Id'] ?? 0);
            if ($id > 0) {
                $rate_ids[] = $id;
            }
        }

        $rate_days = $this->get_days_for_rate_ids($rate_ids);

        foreach ($rates as &$rate) {
            $id = intval($rate['Id'] ?? 0);
            $days = $id > 0 ? ($rate_days[$id] ?? []) : [];

            if (empty($days)) {
                $legacy_day = $rate['DayOfWeek'] ?? null;
                if ($legacy_day !== null && $legacy_day !== '') {
                    $legacy = intval($legacy_day);
                    if ($legacy >= 0 && $legacy <= 6) {
                        $days = [$legacy];
                    }
                }
            }

            $rate['DaysOfWeek'] = $days;
        }
        unset($rate);

        return $rates;
    }

    /**
     * @param array<string, mixed>|null $rate
     * @return array<string, mixed>|null
     */
    public function hydrate_rate_with_days(?array $rate): ?array {
        if (!$rate) {
            return $rate;
        }

        $rates = $this->hydrate_rates_with_days([$rate]);
        return $rates[0] ?? null;
    }

    /**
     * @param array<int, int> $rate_ids
     * @return array<int, array<int, int>>
     */
    public function get_days_for_rate_ids(array $rate_ids): array {
        $rate_ids = array_values(array_filter(array_map('intval', $rate_ids)));
        if (empty($rate_ids) || !$this->day_table_exists()) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($rate_ids), '%d'));
        $sql = $this->wpdb->prepare(
            "SELECT RoomRateId, DayOfWeek FROM {$this->day_table_name} WHERE RoomRateId IN ({$placeholders})",
            ...$rate_ids
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $rate_id = intval($row['RoomRateId'] ?? 0);
            $day = intval($row['DayOfWeek'] ?? -1);
            if ($rate_id <= 0 || $day < 0 || $day > 6) {
                continue;
            }

            $map[$rate_id][$day] = $day;
        }

        foreach ($map as $rate_id => $days) {
            ksort($days);
            $map[$rate_id] = array_values($days);
        }

        return $map;
    }

    /**
     * @param array<int, mixed> $days
     */
    public function replace_days_for_rate(int $rate_id, array $days): bool {
        if (!$this->day_table_exists()) {
            return true;
        }

        $normalized_days = $this->normalize_days($days);

        $deleted = $this->wpdb->delete($this->day_table_name, ['RoomRateId' => $rate_id], ['%d']);
        if ($deleted === false) {
            return false;
        }

        foreach ($normalized_days as $day) {
            $inserted = $this->wpdb->insert(
                $this->day_table_name,
                [
                    'RoomRateId' => $rate_id,
                    'DayOfWeek' => $day,
                ],
                ['%d', '%d']
            );

            if ($inserted === false) {
                return false;
            }
        }

        return true;
    }

    public function get_by_id($id): ?array {
        $rate = parent::get_by_id($id);
        return $this->hydrate_rate_with_days($rate);
    }

    public function get_all($args = []): array {
        $rates = parent::get_all($args);
        return $this->hydrate_rates_with_days($rates);
    }

    public function get_by_room($room_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE RoomId = %d",
            $room_id
        );

        return $this->hydrate_rates_with_days($this->wpdb->get_results($sql, ARRAY_A) ?: []);
    }

    public function get_room_ids_with_active_rates(array $room_ids = []): array {
        $room_ids = array_values(array_filter(array_map('intval', $room_ids)));

        if (!empty($room_ids)) {
            $placeholders = implode(', ', array_fill(0, count($room_ids), '%d'));
            $sql = $this->wpdb->prepare(
                "SELECT DISTINCT RoomId FROM {$this->table_name}
                 WHERE IsActive = 1
                 AND RoomId IN ({$placeholders})",
                ...$room_ids
            );
        } else {
            $sql = "SELECT DISTINCT RoomId FROM {$this->table_name} WHERE IsActive = 1";
        }

        return array_values(array_map('intval', $this->wpdb->get_col($sql) ?: []));
    }

    public function get_active_room_rate( mixed $room_id, mixed $org_type_id = null ): ?array {
        if ( $org_type_id ) {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE RoomId = %d
                 AND OrganisationTypeId = %d
                 AND IsActive = 1
                 ORDER BY Priority DESC
                 LIMIT 1",
                $room_id,
                $org_type_id
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE RoomId = %d
                 AND OrganisationTypeId IS NULL
                 AND IsActive = 1
                 ORDER BY Priority DESC
                 LIMIT 1",
                $room_id
            );
        }

        return $this->hydrate_rate_with_days($this->wpdb->get_row( $sql, ARRAY_A ));
    }

    /**
     * Return active rates for a room and scope ordered by priority.
     *
     * @param int $room_id
     * @param int|null $org_type_id
     * @param string|null $validity_date Y-m-d reference date for ValidFrom/ValidTo filtering.
     * @return array<int, array<string, mixed>>
     */
    public function get_active_rates_for_scope(int $room_id, ?int $org_type_id = null, ?string $validity_date = null): array {
        $params = [
            $room_id,
        ];

        $where_org = 'AND OrganisationTypeId IS NULL';
        if ($org_type_id !== null && $org_type_id > 0) {
            $where_org = 'AND OrganisationTypeId = %d';
            $params[] = $org_type_id;
        }

        $where_validity = '';
        if (!empty($validity_date)) {
            $where_validity = 'AND (ValidFrom IS NULL OR ValidFrom <= %s)
                 AND (ValidTo IS NULL OR ValidTo >= %s)';
            $params[] = $validity_date;
            $params[] = $validity_date;
        }

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE RoomId = %d
             {$where_org}
             AND IsActive = 1
             {$where_validity}
             ORDER BY Priority DESC, Id DESC",
            ...$params
        );

        return $this->hydrate_rates_with_days($this->wpdb->get_results($sql, ARRAY_A) ?: []);
    }
}
