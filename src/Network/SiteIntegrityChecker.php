<?php
namespace MYVH\Network;

if (!defined('ABSPATH')) {
    exit;
}

class SiteIntegrityChecker {
    private $wpdb;

    public function __construct(?\wpdb $wpdb_instance = null) {
        global $wpdb;
        $this->wpdb = $wpdb_instance instanceof \wpdb ? $wpdb_instance : $wpdb;
    }

    /**
     * @return array<string, mixed>
     */
    public function run_for_current_site(): array {
        if (!$this->wpdb instanceof \wpdb) {
            return [
                'status' => 'failed',
                'error_count' => 1,
                'warning_count' => 0,
                'summary' => 'WordPress database connection is not available.',
                'findings' => [
                    $this->finding(
                        'runtime.wpdb_missing',
                        'error',
                        'Integrity checks cannot run because the WordPress database connection is unavailable.'
                    ),
                ],
            ];
        }

        $findings = [];

        $schema = $this->run_schema_checks();
        $findings = array_merge($findings, $schema);

        $referential = $this->run_referential_checks();
        $findings = array_merge($findings, $referential);

        $bookability = $this->run_bookability_checks();
        $findings = array_merge($findings, $bookability);

        $error_count = count(array_filter($findings, static fn(array $f): bool => ($f['severity'] ?? '') === 'error'));
        $warning_count = count(array_filter($findings, static fn(array $f): bool => ($f['severity'] ?? '') === 'warning'));

        $status = $error_count > 0 ? 'failed' : 'completed';
        $summary = sprintf('Errors: %d, Warnings: %d', $error_count, $warning_count);

        return [
            'status' => $status,
            'error_count' => $error_count,
            'warning_count' => $warning_count,
            'summary' => $summary,
            'findings' => $findings,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function run_schema_checks(): array {
        $findings = [];

        $required_tables = [
            'myvh_rooms',
            'myvh_room_rates',
            'myvh_room_rate_days',
            'myvh_bookings',
            'myvh_customers',
            'myvh_organisations',
            'myvh_booking_charges',
            'myvh_booking_addons',
            'myvh_booking_discounts',
            'myvh_addons',
            'myvh_discounts',
            'myvh_recurring_patterns',
            'myvh_invoices',
            'myvh_invoice_items',
            'myvh_payments',
        ];

        foreach ($required_tables as $suffix) {
            $full_table = $this->wpdb->prefix . $suffix;
            if (!$this->table_exists($full_table)) {
                $findings[] = $this->finding(
                    'schema.missing_table',
                    'error',
                    sprintf('Missing required table: %s', $full_table),
                    ['table' => $full_table]
                );
            }
        }

        $required_columns = [
            'myvh_bookings' => ['Id', 'CustomerId', 'OrganisationId', 'RoomId', 'StartDate', 'EndDate', 'StartTime', 'EndTime'],
            'myvh_rooms' => ['Id', 'Name', 'OpeningTime', 'ClosingTime'],
            'myvh_room_rates' => ['Id', 'RoomId', 'OrganisationTypeId', 'DayOfWeek', 'StartTime', 'EndTime', 'IsActive', 'ValidFrom', 'ValidTo'],
            'myvh_booking_charges' => ['Id', 'BookingId', 'RoomRateId'],
            'myvh_invoice_items' => ['Id', 'InvoiceId', 'BookingId'],
            'myvh_payments' => ['Id', 'InvoiceId'],
        ];

        foreach ($required_columns as $suffix => $columns) {
            $full_table = $this->wpdb->prefix . $suffix;
            if (!$this->table_exists($full_table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!$this->column_exists($full_table, $column)) {
                    $findings[] = $this->finding(
                        'schema.missing_column',
                        'error',
                        sprintf('Missing required column %s.%s', $full_table, $column),
                        [
                            'table' => $full_table,
                            'column' => $column,
                        ]
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function run_referential_checks(): array {
        $findings = [];

        $relations = [
            [
                'key' => 'referential.bookings.customer',
                'tables' => ['myvh_bookings', 'myvh_customers'],
                'sql' => "SELECT COUNT(*) FROM {$this->wpdb->prefix}myvh_bookings b LEFT JOIN {$this->wpdb->prefix}myvh_customers c ON c.Id = b.CustomerId WHERE c.Id IS NULL",
                'message' => 'Bookings reference missing customers',
            ],
            [
                'key' => 'referential.bookings.room',
                'tables' => ['myvh_bookings', 'myvh_rooms'],
                'sql' => "SELECT COUNT(*) FROM {$this->wpdb->prefix}myvh_bookings b LEFT JOIN {$this->wpdb->prefix}myvh_rooms r ON r.Id = b.RoomId WHERE r.Id IS NULL",
                'message' => 'Bookings reference missing rooms',
            ],
            [
                'key' => 'referential.bookings.organisation',
                'tables' => ['myvh_bookings', 'myvh_organisations'],
                'sql' => "SELECT COUNT(*) FROM {$this->wpdb->prefix}myvh_bookings b LEFT JOIN {$this->wpdb->prefix}myvh_organisations o ON o.Id = b.OrganisationId WHERE b.OrganisationId > 0 AND o.Id IS NULL",
                'message' => 'Bookings reference missing organisations',
            ],
            [
                'key' => 'referential.booking_charges.booking',
                'tables' => ['myvh_booking_charges', 'myvh_bookings'],
                'sql' => "SELECT COUNT(*) FROM {$this->wpdb->prefix}myvh_booking_charges bc LEFT JOIN {$this->wpdb->prefix}myvh_bookings b ON b.Id = bc.BookingId WHERE b.Id IS NULL",
                'message' => 'Booking charges reference missing bookings',
            ],
            [
                'key' => 'referential.booking_charges.room_rate',
                'tables' => ['myvh_booking_charges', 'myvh_room_rates'],
                'sql' => "SELECT COUNT(*) FROM {$this->wpdb->prefix}myvh_booking_charges bc LEFT JOIN {$this->wpdb->prefix}myvh_room_rates rr ON rr.Id = bc.RoomRateId WHERE rr.Id IS NULL",
                'message' => 'Booking charges reference missing room rates',
            ],
            [
                'key' => 'referential.booking_addons.booking',
                'tables' => ['myvh_booking_addons', 'myvh_bookings'],
                'sql' => "SELECT COUNT(*) FROM {$this->wpdb->prefix}myvh_booking_addons ba LEFT JOIN {$this->wpdb->prefix}myvh_bookings b ON b.Id = ba.BookingId WHERE b.Id IS NULL",
                'message' => 'Booking addons reference missing bookings',
            ],
            [
                'key' => 'referential.booking_addons.addon',
                'tables' => ['myvh_booking_addons', 'myvh_addons'],
                'sql' => "SELECT COUNT(*) FROM {$this->wpdb->prefix}myvh_booking_addons ba LEFT JOIN {$this->wpdb->prefix}myvh_addons a ON a.Id = ba.AddonId WHERE a.Id IS NULL",
                'message' => 'Booking addons reference missing addons',
            ],
            [
                'key' => 'referential.booking_discounts.booking',
                'tables' => ['myvh_booking_discounts', 'myvh_bookings'],
                'sql' => "SELECT COUNT(*) FROM {$this->wpdb->prefix}myvh_booking_discounts bd LEFT JOIN {$this->wpdb->prefix}myvh_bookings b ON b.Id = bd.BookingId WHERE b.Id IS NULL",
                'message' => 'Booking discounts reference missing bookings',
            ],
            [
                'key' => 'referential.booking_discounts.discount',
                'tables' => ['myvh_booking_discounts', 'myvh_discounts'],
                'sql' => "SELECT COUNT(*) FROM {$this->wpdb->prefix}myvh_booking_discounts bd LEFT JOIN {$this->wpdb->prefix}myvh_discounts d ON d.Id = bd.DiscountId WHERE bd.DiscountId IS NOT NULL AND bd.DiscountId > 0 AND d.Id IS NULL",
                'message' => 'Booking discounts reference missing discounts',
            ],
            [
                'key' => 'referential.invoice_items.invoice',
                'tables' => ['myvh_invoice_items', 'myvh_invoices'],
                'sql' => "SELECT COUNT(*) FROM {$this->wpdb->prefix}myvh_invoice_items ii LEFT JOIN {$this->wpdb->prefix}myvh_invoices i ON i.Id = ii.InvoiceId WHERE i.Id IS NULL",
                'message' => 'Invoice items reference missing invoices',
            ],
            [
                'key' => 'referential.invoice_items.booking',
                'tables' => ['myvh_invoice_items', 'myvh_bookings'],
                'sql' => "SELECT COUNT(*) FROM {$this->wpdb->prefix}myvh_invoice_items ii LEFT JOIN {$this->wpdb->prefix}myvh_bookings b ON b.Id = ii.BookingId WHERE ii.BookingId IS NOT NULL AND ii.BookingId > 0 AND b.Id IS NULL",
                'message' => 'Invoice items reference missing bookings',
            ],
            [
                'key' => 'referential.payments.invoice',
                'tables' => ['myvh_payments', 'myvh_invoices'],
                'sql' => "SELECT COUNT(*) FROM {$this->wpdb->prefix}myvh_payments p LEFT JOIN {$this->wpdb->prefix}myvh_invoices i ON i.Id = p.InvoiceId WHERE i.Id IS NULL",
                'message' => 'Payments reference missing invoices',
            ],
            [
                'key' => 'referential.recurring_patterns.parent_booking',
                'tables' => ['myvh_recurring_patterns', 'myvh_bookings'],
                'sql' => "SELECT COUNT(*) FROM {$this->wpdb->prefix}myvh_recurring_patterns rp LEFT JOIN {$this->wpdb->prefix}myvh_bookings b ON b.Id = rp.ParentBookingId WHERE b.Id IS NULL",
                'message' => 'Recurring patterns reference missing parent bookings',
            ],
        ];

        foreach ($relations as $relation) {
            if (!$this->all_tables_exist($relation['tables'] ?? [])) {
                continue;
            }

            $count = (int) $this->wpdb->get_var($relation['sql']);

            if ($count > 0) {
                $findings[] = $this->finding(
                    $relation['key'],
                    'error',
                    sprintf('%s (%d)', $relation['message'], $count),
                    ['orphan_count' => $count]
                );
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function run_bookability_checks(): array {
        $findings = [];

        $rooms_table = $this->wpdb->prefix . 'myvh_rooms';
        $rates_table = $this->wpdb->prefix . 'myvh_room_rates';
        $rate_days_table = $this->wpdb->prefix . 'myvh_room_rate_days';

        if (!$this->table_exists($rooms_table) || !$this->table_exists($rates_table)) {
            return $findings;
        }

        $rooms = $this->wpdb->get_results("SELECT Id, Name, OpeningTime, ClosingTime FROM {$rooms_table} ORDER BY Id ASC", ARRAY_A);
        if (!is_array($rooms)) {
            return $findings;
        }

        $today = current_time('Y-m-d');

        foreach ($rooms as $room) {
            $room_id = (int) ($room['Id'] ?? 0);
            if ($room_id <= 0) {
                continue;
            }

                        if ($this->table_exists($rate_days_table)) {
                                $rates_sql = "SELECT rr.*, GROUP_CONCAT(DISTINCT rrd.DayOfWeek ORDER BY rrd.DayOfWeek SEPARATOR ',') AS DaysOfWeek
                                        FROM {$rates_table} rr
                                        LEFT JOIN {$rate_days_table} rrd ON rrd.RoomRateId = rr.Id
                                        WHERE rr.RoomId = %d
                                            AND rr.IsActive = 1
                                            AND (rr.ValidFrom IS NULL OR rr.ValidFrom <= %s)
                                            AND (rr.ValidTo IS NULL OR rr.ValidTo >= %s)
                                        GROUP BY rr.Id
                                        ORDER BY rr.Priority DESC, rr.Id DESC";
                        } else {
                                $rates_sql = "SELECT rr.*, NULL AS DaysOfWeek
                                        FROM {$rates_table} rr
                                        WHERE rr.RoomId = %d
                                            AND rr.IsActive = 1
                                            AND (rr.ValidFrom IS NULL OR rr.ValidFrom <= %s)
                                            AND (rr.ValidTo IS NULL OR rr.ValidTo >= %s)
                                        ORDER BY rr.Priority DESC, rr.Id DESC";
                        }

            $rates = $this->wpdb->get_results(
                $this->wpdb->prepare($rates_sql, $room_id, $today, $today),
                ARRAY_A
            );

            if (!is_array($rates) || empty($rates)) {
                $findings[] = $this->finding(
                    'bookability.room.no_active_rate',
                    'error',
                    sprintf('Room "%s" has no active room rates for today', (string) ($room['Name'] ?? 'Unknown')),
                    ['room_id' => $room_id]
                );
                continue;
            }

            $opening = $this->time_to_minutes((string) ($room['OpeningTime'] ?? '00:00:00'));
            $closing = $this->time_to_minutes((string) ($room['ClosingTime'] ?? '23:59:59'));

            if ($closing <= $opening) {
                continue;
            }

            for ($day = 0; $day <= 6; $day++) {
                $intervals = [];

                foreach ($rates as $rate) {
                    if (!$this->rate_applies_to_day($rate, $day)) {
                        continue;
                    }

                    $start = $opening;
                    $end = $closing;

                    if (!empty($rate['StartTime'])) {
                        $start = max($start, $this->time_to_minutes((string) $rate['StartTime']));
                    }

                    if (!empty($rate['EndTime'])) {
                        $end = min($end, $this->time_to_minutes((string) $rate['EndTime']));
                    }

                    if ($end > $start) {
                        $intervals[] = ['start' => $start, 'end' => $end];
                    }
                }

                $uncovered = $this->find_uncovered_ranges($opening, $closing, $intervals);

                if (!empty($uncovered)) {
                    $findings[] = $this->finding(
                        'bookability.room.rate_gap',
                        'error',
                        sprintf('Room "%s" has rate coverage gaps on %s', (string) ($room['Name'] ?? 'Unknown'), $this->day_label($day)),
                        [
                            'room_id' => $room_id,
                            'day' => $day,
                            'uncovered_ranges' => array_map(function (array $gap): string {
                                return $this->minutes_to_time($gap['start']) . '-' . $this->minutes_to_time($gap['end']);
                            }, $uncovered),
                        ]
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $rate
     */
    private function rate_applies_to_day(array $rate, int $day): bool {
        $days = [];

        if (!empty($rate['DaysOfWeek']) && is_string($rate['DaysOfWeek'])) {
            $parts = explode(',', $rate['DaysOfWeek']);
            foreach ($parts as $part) {
                $value = intval(trim($part));
                if ($value >= 0 && $value <= 6) {
                    $days[$value] = $value;
                }
            }
        }

        if (!empty($days)) {
            return isset($days[$day]);
        }

        if ($rate['DayOfWeek'] === null || $rate['DayOfWeek'] === '') {
            return true;
        }

        return intval($rate['DayOfWeek']) === $day;
    }

    /**
     * @param array<int, array{start:int,end:int}> $intervals
     * @return array<int, array{start:int,end:int}>
     */
    private function find_uncovered_ranges(int $opening, int $closing, array $intervals): array {
        if (empty($intervals)) {
            return [['start' => $opening, 'end' => $closing]];
        }

        usort($intervals, static fn(array $a, array $b): int => $a['start'] <=> $b['start']);

        $merged = [];
        foreach ($intervals as $interval) {
            if (empty($merged)) {
                $merged[] = $interval;
                continue;
            }

            $last_index = count($merged) - 1;
            if ($interval['start'] <= $merged[$last_index]['end']) {
                $merged[$last_index]['end'] = max($merged[$last_index]['end'], $interval['end']);
                continue;
            }

            $merged[] = $interval;
        }

        $gaps = [];
        $cursor = $opening;

        foreach ($merged as $interval) {
            if ($interval['start'] > $cursor) {
                $gaps[] = ['start' => $cursor, 'end' => $interval['start']];
            }
            $cursor = max($cursor, $interval['end']);
        }

        if ($cursor < $closing) {
            $gaps[] = ['start' => $cursor, 'end' => $closing];
        }

        return array_values(array_filter($gaps, static fn(array $gap): bool => $gap['end'] > $gap['start']));
    }

    private function table_exists(string $table): bool {
        $table_like = $this->wpdb->esc_like($table);
        $result = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table_like));

        return !empty($result);
    }

    private function column_exists(string $table, string $column): bool {
        $result = $this->wpdb->get_var($this->wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));

        return $result !== null;
    }

    /**
     * @param array<int, string> $table_suffixes
     */
    private function all_tables_exist(array $table_suffixes): bool {
        foreach ($table_suffixes as $suffix) {
            if (!$this->table_exists($this->wpdb->prefix . $suffix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(string $check_key, string $severity, string $message, array $details = []): array {
        return [
            'check_key' => $check_key,
            'severity' => $severity,
            'message' => $message,
            'details' => $details,
        ];
    }

    private function time_to_minutes(string $value): int {
        $parts = explode(':', $value);
        $hour = isset($parts[0]) ? intval($parts[0]) : 0;
        $minute = isset($parts[1]) ? intval($parts[1]) : 0;

        return max(0, ($hour * 60) + $minute);
    }

    private function minutes_to_time(int $minutes): string {
        $minutes = max(0, min(24 * 60, $minutes));
        $hour = (int) floor($minutes / 60);
        $minute = $minutes % 60;

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function day_label(int $day): string {
        $labels = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return $labels[$day] ?? (string) $day;
    }
}
