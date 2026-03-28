<?php
/**
 * Repository class for myvh_bookings table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class BookingRepository extends RepositoryBase {

    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_bookings';
    }


    /**
     * Get all bookings belonging to a recurring pattern
     */
    /**
     * Get all bookings with customer, room, venue, and pattern details in one query.
    * Supports optional booking / status / room / customer filters.
     *
     * @param array $args orderby, order, booking_id, status, room_id, customer_id
     * @return array
     */
    public function get_all_with_details($args = []): array {
        $defaults = ['orderby'      => 'b.StartDate',
                     'order'        => 'DESC',
                     'booking_id'   => 0,
                     'status'       => '',
                     'room_id'      => 0,
                     'customer_id'  => 0];
        $args = wp_parse_args($args, $defaults);

        $where  = ['1=1'];
        $params = [];

        if (!empty($args['booking_id'])) {
            $where[]  = 'b.Id = %d';
            $params[] = intval($args['booking_id']);
        }
        if (!empty($args['status'])) {
            $where[]  = 'b.Status = %s';
            $params[] = $args['status'];
        }
        if (!empty($args['room_id'])) {
            $where[]  = 'b.RoomId = %d';
            $params[] = intval($args['room_id']);
        }
        if (!empty($args['customer_id'])) {
            $where[]  = 'b.CustomerId = %d';
            $params[] = intval($args['customer_id']);
        }

        $where_sql = implode(' AND ', $where);
        $order_sql = esc_sql($args['orderby']) . ' ' . ('ASC' === strtoupper($args['order']) ? 'ASC' : 'DESC');

        $sql = "SELECT
                    b.*,
                    c.Name  AS CustomerName,
                    c.Email AS CustomerEmail,
                    o.Name  AS OrganisationName,
                    r.Name  AS RoomName,
                    v.Name  AS VenueName,
                    rp.RecurrenceType,
                    rp.RecurrenceInterval,
                    rp.RecurrenceDay,
                    rp.RecurrenceWeek,
                    rp.StartDate  AS PatternStartDate,
                    rp.EndDate    AS PatternEndDate,
                    rp.IsActive   AS PatternIsActive
                FROM {$this->table_name} b
                LEFT JOIN {$this->wpdb->prefix}myvh_customers c              ON b.CustomerId         = c.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_organisations o          ON b.OrganisationId     = o.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_rooms r                  ON b.RoomId             = r.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_venues v                 ON r.VenueId            = v.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_recurring_patterns rp    ON b.RecurringPatternId = rp.Id
                WHERE {$where_sql}
                ORDER BY {$order_sql}";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, ...$params);
        }

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if ($results === null) {
            error_log('MYVH Booking Repository Error (get_all_with_details): ' . $this->wpdb->last_error);
        }

        return $results ?? [];
    }

    public function get_by_pattern_id($pattern_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT b.*, c.Name as CustomerName, r.Name as RoomName
             FROM {$this->table_name} b
             LEFT JOIN {$this->wpdb->prefix}myvh_customers c ON b.CustomerId = c.Id
             LEFT JOIN {$this->wpdb->prefix}myvh_rooms r ON b.RoomId = r.Id
             WHERE b.RecurringPatternId = %d
             ORDER BY b.StartDate ASC",
            $pattern_id
        );
        return $this->wpdb->get_results($sql, ARRAY_A) ?? [];
    }

    /**
     * Cancel all future bookings in a pattern (today and beyond)
     */
    public function cancel_future_by_pattern($pattern_id): int|false {
        return $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->table_name}
             SET Status = %s
             WHERE RecurringPatternId = %d AND StartDate >= %s AND Status != %s",
                BookingStatus::CANCELLED,
                $pattern_id,
                date('Y-m-d'),
                BookingStatus::CANCELLED
        ));
    }

    /**
     * Delete all future bookings in a pattern (strictly after today)
     */
    public function delete_future_by_pattern($pattern_id): int|false {
        return $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->table_name}
             WHERE RecurringPatternId = %d AND StartDate > %s",
            $pattern_id,
            date('Y-m-d')
        ));
    }

    /**
     * Check whether a proposed booking overlaps an existing non-cancelled booking.
     *
     * Overlap rule: existing_start < new_end AND existing_end > new_start
     *
     * @param int         $room_id             Room to check.
     * @param string      $date                Start date (Y-m-d).
     * @param string      $start_time          Start time (H:i or H:i:s).
     * @param string      $end_time            End time (H:i or H:i:s).
     * @param int|null    $exclude_booking_id  Booking ID to ignore (e.g. when updating).
     * @param string|null $end_date            Optional end date (Y-m-d), defaults to $date.
     * @return bool True if a conflict exists.
     */
    public function has_conflict($room_id, $date, $start_time, $end_time, $exclude_booking_id = null, $end_date = null): bool {

        $start_date = sanitize_text_field($date);
        $resolved_end_date = sanitize_text_field($end_date ?: $start_date);

        $normalized_start_time = $this->normalize_time($start_time);
        $normalized_end_time = $this->normalize_time($end_time);

        $new_start = $start_date . ' ' . $normalized_start_time;
        $new_end = $resolved_end_date . ' ' . $normalized_end_time;

        if (strtotime($new_end) <= strtotime($new_start)) {
            return true;
        }

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE RoomId = %d
             AND Status != %s
             AND CONCAT(StartDate, ' ', StartTime) < %s
             AND CONCAT(EndDate, ' ', EndTime) > %s",
            $room_id,
            BookingStatus::CANCELLED,
            $new_end,
            $new_start
        );

        if ($exclude_booking_id) {
            $sql .= $this->wpdb->prepare(' AND Id != %d', $exclude_booking_id);
        }

        return (int) $this->wpdb->get_var($sql) > 0;
    }

    private function normalize_time($time): string {
        $value = sanitize_text_field((string) $time);

        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value . ':00';
        }

        return $value;
    }

    private function normalize_datetime($value): string {
        $raw = sanitize_text_field((string) $value);
        if ($raw === '') {
            return '';
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Count how many bookings exist for a given customer.
     *
     * @param int $customer_id
     * @return int
     */
    public function count_by_customer(int $customer_id): int {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE CustomerId = %d",
                $customer_id
            )
        );
    }

    public function get_conflicts($room_id, $start, $end): array {
        $new_start = $this->normalize_datetime($start);
        $new_end = $this->normalize_datetime($end);

        if (!$new_start || !$new_end || strtotime($new_end) <= strtotime($new_start)) {
            return [];
        }

        $sql = $this->wpdb->prepare(
            "SELECT Id FROM {$this->table_name}
            WHERE RoomId = %d
            AND Status != %s
            AND CONCAT(StartDate, ' ', StartTime) < %s
            AND CONCAT(EndDate, ' ', EndTime) > %s
            LIMIT 1",
            $room_id,
            BookingStatus::CANCELLED,
            $new_end,
            $new_start
        );

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function get_between($start, $end, $context = null, $filters = []): array {
        $defaults = [
            'room_id' => 0,
            'venue_id' => 0,
            'statuses' => [],
        ];
        $filters = wp_parse_args($filters, $defaults);

        $where = [ 'b.StartDate < %s', 'b.EndDate >= %s' ];
        $params = [ $end, $start ];

        if (!empty($filters['room_id'])) {
            $where[] = 'b.RoomId = %d';
            $params[] = (int) $filters['room_id'];
        }

        if (!empty($filters['venue_id'])) {
            $where[] = 'r.VenueId = %d';
            $params[] = (int) $filters['venue_id'];
        }

        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $statuses = array_values(array_filter(array_map('sanitize_text_field', $filters['statuses'])));
            if (!empty($statuses)) {
                $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
                $where[] = "b.Status IN ({$placeholders})";
                $params = array_merge($params, $statuses);
            }
        }

        $where_sql = implode(' AND ', $where);

        $sql = "SELECT b.*, r.Name AS RoomName, v.Name AS VenueName
            FROM {$this->table_name} b
            LEFT JOIN {$this->wpdb->prefix}myvh_rooms r ON b.RoomId = r.Id
            LEFT JOIN {$this->wpdb->prefix}myvh_venues v ON r.VenueId = v.Id
            WHERE {$where_sql}
            ORDER BY b.StartDate ASC, b.StartTime ASC";

        $sql = $this->wpdb->prepare($sql, ...$params);

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function get_upcoming_bookings($customer_id): array {

        //$date = new datetime();
        $start = date('Y-m-d');
        $sql = $this->wpdb->prepare(
            "SELECT b.*, r.Name as RoomName FROM {$this->table_name} b
            LEFT JOIN {$this->wpdb->prefix}myvh_rooms r ON b.RoomId = r.Id
            WHERE StartDate >= %s
            AND   CustomerId = %d",
            $start,
            intval($customer_id));

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function move_booking($id, $start, $end, $room): int|WP_Error {

        // DayPilot sends full ISO datetimes: "2025-06-14T09:00:00"
        // Split into date and time parts before storing.
        $start_parts = explode( 'T', $start );
        $end_parts   = explode( 'T', $end );

        $start_date = $start_parts[0];
        $start_time = $start_parts[1] ?? '00:00:00';
        $end_date   = $end_parts[0];
        $end_time   = $end_parts[1] ?? '00:00:00';

        $sql = $this->wpdb->prepare(
            "UPDATE {$this->table_name}
            SET StartDate = %s,
                StartTime = %s,
                EndDate   = %s,
                EndTime   = %s,
                RoomId    = %d
            WHERE Id = %d",
            $start_date,
            $start_time,
            $end_date,
            $end_time,
            $room,
            $id
        );

        return $this->wpdb->query($sql);
    }


    /**
     * Get uninvoiced bookings (confirmed or completed status, no non-cancelled invoices)
     *
     * @param array $args Optional query arguments (orderby, order, limit, offset, organisation_id, customer_id)
     * @return array|null Array of uninvoiced bookings with customer/org details
     */
    public function get_uninvoiced_bookings($args = []): array {
        $defaults = [
            'orderby' => 'b.StartDate',
            'order' => 'DESC',
            'limit' => null,
            'offset' => null,
            'organisation_id' => 0,
            'customer_id' => 0
        ];
        $args = wp_parse_args($args, $defaults);

        $where = [
            "b.Status IN ('confirmed', 'completed')",
            "ii.Id IS NULL"  // No invoice items linked
        ];
        $params = [];

        if (!empty($args['organisation_id'])) {
            $where[] = 'b.OrganisationId = %d';
            $params[] = intval($args['organisation_id']);
        }

        if (!empty($args['customer_id'])) {
            $where[] = 'b.CustomerId = %d';
            $params[] = intval($args['customer_id']);
        }

        $where_sql = implode(' AND ', $where);
        $order_sql = esc_sql($args['orderby']) . ' ' . ('ASC' === strtoupper($args['order']) ? 'ASC' : 'DESC');
        $limit_sql = '';

        if ($args['limit'] !== null) {
            $limit_sql = ' LIMIT ' . intval($args['limit']);
            if ($args['offset'] !== null) {
                $limit_sql .= ' OFFSET ' . intval($args['offset']);
            }
        }

        $sql = "SELECT
                    b.*,
                    c.Name AS CustomerName,
                    c.Email AS CustomerEmail,
                    o.Name AS OrganisationName,
                    r.Name AS RoomName,
                    v.Name AS VenueName
                FROM {$this->table_name} b
                LEFT JOIN {$this->wpdb->prefix}myvh_customers c ON b.CustomerId = c.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_organisations o ON b.OrganisationId = o.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_rooms r ON b.RoomId = r.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_venues v ON r.VenueId = v.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_invoice_items ii ON b.Id = ii.BookingId
                LEFT JOIN {$this->wpdb->prefix}myvh_invoices i ON ii.InvoiceId = i.Id
                    AND i.Status NOT IN ('cancelled')
                WHERE $where_sql
                GROUP BY b.Id
                HAVING COUNT(i.Id) = 0
                ORDER BY $order_sql
                $limit_sql";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, ...$params);
        }

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if ($results === null && $this->wpdb->last_error) {
            error_log('MYVH Booking Repository Error (get_uninvoiced_bookings): ' . $this->wpdb->last_error);
        }

        return $results ?: [];
    }

    /**
     * Count uninvoiced bookings by organisation
     *
     * @return array Array of [OrganisationId => count] pairs
     */
    public function count_uninvoiced_by_organisation(): array {
        $sql = "SELECT
                    b.OrganisationId,
                    o.Name AS OrganisationName,
                    COUNT(DISTINCT b.Id) AS UninvoicedCount
                FROM {$this->table_name} b
                LEFT JOIN {$this->wpdb->prefix}myvh_organisations o ON b.OrganisationId = o.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_invoice_items ii ON b.Id = ii.BookingId
                LEFT JOIN {$this->wpdb->prefix}myvh_invoices i ON ii.InvoiceId = i.Id
                    AND i.Status NOT IN ('cancelled')
                WHERE b.Status IN ('confirmed', 'completed')
                    AND b.OrganisationId IS NOT NULL
                GROUP BY b.OrganisationId, o.Name
                HAVING COUNT(i.Id) = 0
                ORDER BY o.Name ASC";

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if ($results === null && $this->wpdb->last_error) {
            error_log('MYVH Booking Repository Error (count_uninvoiced_by_organisation): ' . $this->wpdb->last_error);
            return [];
        }

        return $results ?: [];
    }

    /**
     * Count uninvoiced bookings by customer
     *
     * @return array Array of [CustomerId => count] pairs
     */
    public function count_uninvoiced_by_customer(): array {
        $sql = "SELECT
                    b.CustomerId,
                    c.Name AS CustomerName,
                    c.Email AS CustomerEmail,
                    COUNT(DISTINCT b.Id) AS UninvoicedCount
                FROM {$this->table_name} b
                LEFT JOIN {$this->wpdb->prefix}myvh_customers c ON b.CustomerId = c.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_invoice_items ii ON b.Id = ii.BookingId
                LEFT JOIN {$this->wpdb->prefix}myvh_invoices i ON ii.InvoiceId = i.Id
                    AND i.Status NOT IN ('cancelled')
                WHERE b.Status IN ('confirmed', 'completed')
                GROUP BY b.CustomerId, c.Name, c.Email
                HAVING COUNT(i.Id) = 0
                ORDER BY c.Name ASC";

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if ($results === null && $this->wpdb->last_error) {
            error_log('MYVH Booking Repository Error (count_uninvoiced_by_customer): ' . $this->wpdb->last_error);
            return [];
        }

        return $results ?: [];
    }

    /**
     * Check if a booking has any non-cancelled invoices (for mutual exclusivity)
     *
     * @param int $booking_id The booking ID to check
     * @return bool True if booking has any non-cancelled invoices, false otherwise
     */
    public function has_invoiced_items($booking_id): bool {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(DISTINCT i.Id) AS invoice_count
            FROM {$this->wpdb->prefix}myvh_invoice_items ii
            LEFT JOIN {$this->wpdb->prefix}myvh_invoices i ON ii.InvoiceId = i.Id
            WHERE ii.BookingId = %d
                AND i.Status NOT IN ('cancelled')",
            intval($booking_id)
        );

        $result = $this->wpdb->get_var($sql);
        return intval($result) > 0;
    }
}
