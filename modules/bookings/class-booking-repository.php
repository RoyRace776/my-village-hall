<?php
/**
 * Repository class for myvh_bookings table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MYVH_Booking_Repository {

    /**
     * @var wpdb
     */
    private $wpdb;

    /**
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_bookings';
    }

    /**
     * Create a new record
     *
     * @param array $data Associative array of column => value pairs
     * @return int|false The inserted record ID on success, false on failure
     */
    public function create($data) {
        $result = $this->wpdb->insert(
            $this->table_name,
            $data,
            $this->get_format($data)
        );

        if ($result === false) {
            error_log('MYVH Booking Repository Error (create): ' . $this->wpdb->last_error);
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Get all records
     *
     * @param array $args Optional query arguments (orderby, order, limit, offset)
     * @return array|null Array of records or null on failure
     */
    public function get_all($args = array()) {
        $defaults = array(
            'orderby' => 'Id',
            'order' => 'ASC',
            'limit' => null,
            'offset' => null
        );

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM $this->table_name";
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";

        if ($args['limit'] !== null) {
            $sql .= " LIMIT " . intval($args['limit']);

            if ($args['offset'] !== null) {
                $sql .= " OFFSET " . intval($args['offset']);
            }
        }

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if ($results === null) {
            error_log('MYVH Booking Repository Error (get_all): ' . $this->wpdb->last_error);
        }

        return $results;
    }

    /**
     * Get a record by ID
     *
     * @param int $id The record ID
     * @return array|null The record data or null if not found
     */
    public function get_by_id($id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE Id = %d",
            $id
        );

        $result = $this->wpdb->get_row($sql, ARRAY_A);

        if ($result === null && $this->wpdb->last_error) {
            error_log('MYVH Booking Repository Error (get_by_id): ' . $this->wpdb->last_error);
        }

        return $result;
    }

    /**
     * Update a record
     *
     * @param int $id The record ID
     * @param array $data Associative array of column => value pairs to update
     * @return bool True on success, false on failure
     */
    public function update($data, $id) {
        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            $id,
            $this->get_format($data),
            array('%d')
        );

        if ($result === false) {
            error_log('MYVH Booking Repository Error (update): ' . $this->wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Delete a record
     *
     * @param int $id The record ID
     * @return bool True on success, false on failure
     */
    public function delete($id) {
        $result = $this->wpdb->delete(
            $this->table_name,
            array('Id' => $id),
            array('%d')
        );

        if ($result === false) {
            error_log('MYVH Booking Repository Error (delete): ' . $this->wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Get all bookings belonging to a recurring pattern
     */
    /**
     * Get all bookings with customer, room, venue, and pattern details in one query.
     * Supports optional status / room / customer filters.
     *
     * @param array $args orderby, order, status, room_id, customer_id
     * @return array
     */
    public function get_all_with_details($args = []) {
        $defaults = ['orderby' => 'b.StartDate', 'order' => 'DESC', 'status' => '', 'room_id' => 0, 'customer_id' => 0];
        $args = wp_parse_args($args, $defaults);

        $where  = ['1=1'];
        $params = [];

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

    public function get_by_pattern_id($pattern_id) {
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
    public function cancel_future_by_pattern($pattern_id) {
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
    public function delete_future_by_pattern($pattern_id) {
        return $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->table_name}
             WHERE RecurringPatternId = %d AND StartDate > %s",
            $pattern_id,
            date('Y-m-d')
        ));
    }

    /**
     * Check whether a booking would conflict with any existing confirmed/pending booking.
     *
     * @param int      $room_id             Room to check.
     * @param string   $date                Date (Y-m-d).
     * @param string   $start_time          Start time (H:i:s).
     * @param string   $end_time            End time (H:i:s).
     * @param int|null $exclude_booking_id  Booking ID to ignore (e.g. the parent booking).
     * @return bool True if a conflict exists.
     */
    public function has_conflict($room_id, $date, $start_time, $end_time, $exclude_booking_id = null): bool {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE RoomId = %d
             AND StartDate = %s
             AND Status != %s
             AND (
                 (StartTime < %s AND EndTime > %s) OR
                 (StartTime >= %s AND EndTime <= %s)
             )",
            $room_id,
            $date,
            BookingStatus::CANCELLED,
            $end_time, $start_time,
            $start_time, $end_time
        );

        if ($exclude_booking_id) {
            $sql .= $this->wpdb->prepare(' AND Id != %d', $exclude_booking_id);
        }

        return (int) $this->wpdb->get_var($sql) > 0;
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

    public function get_conflicts($room_id, $start, $end) {
        $sql = $this->wpdb->prepare(
            "SELECT Id FROM {$this->table_name}
            WHERE RoomId = %d
            AND StartTime < %s
            AND EndTime > %s
            LIMIT 1",
            $room_id,
            $end,
            $start
        );

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    //TODO: implement filters on get_between
    public function get_between($start, $end, $filters = []) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name}
            WHERE StartDate >= %s
            AND StartDate <= %s",
            $start,
            $end
        );

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function get_upcoming_bookings($customer_id) {

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

    public function move_booking($id, $start, $end, $room) {
        $sql = $this->wpdb->prepare(
            "UPDATE {$this->table_name}
            SET StartDate = %s,
                EndDate = %s,
                RoomId = %d
            WHERE Id = %d",
            $start,
            $end,
            $room,
            $id
        );

        return $this->wpdb->query($sql);
    }


    /**
     * Get the format array for wpdb operations
     *
     * @param array $data The data array
     * @return array Format array for wpdb
     */
    private function get_format($data) {
        $formats = array();

        foreach ($data as $key => $value) {
            if (is_int($value)) {
                $formats[] = '%d';
            } elseif (is_float($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }
}
