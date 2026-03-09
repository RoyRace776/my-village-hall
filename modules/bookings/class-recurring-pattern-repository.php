<?php
/**
 * Repository class for myvh_recurring_patterns table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MYVH_Recurring_Pattern_Repository {
    
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
        $this->table_name = $wpdb->prefix . 'myvh_recurring_patterns';
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
            error_log('MYVH Recurring_Pattern Repository Error (create): ' . $this->wpdb->last_error);
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
            'order' => 'DESC',
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
            error_log('MYVH Recurring_Pattern Repository Error (get_all): ' . $this->wpdb->last_error);
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
            error_log('MYVH Recurring_Pattern Repository Error (get_by_id): ' . $this->wpdb->last_error);
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
            error_log('MYVH Recurring_Pattern Repository Error (update): ' . $this->wpdb->last_error);
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
            error_log('MYVH Recurring_Pattern Repository Error (delete): ' . $this->wpdb->last_error);
            return false;
        }
        
        return true;
    }

    /**
     * Get all active recurring patterns with booking details
     *
     * @return array|null Array of records or null on failure
     */
    public function get_all_active_with_bookings() {
        $sql = "SELECT 
                    rp.*,
                    b.CustomerId,
                    b.RoomId,
                    b.Description,
                    c.Name as CustomerName,
                    r.Name as RoomName,
                    v.Name as VenueName
                FROM $this->table_name rp
                JOIN {$this->wpdb->prefix}myvh_bookings b ON rp.ParentBookingId = b.Id
                JOIN {$this->wpdb->prefix}myvh_customers c ON b.CustomerId = c.Id
                JOIN {$this->wpdb->prefix}myvh_rooms r ON b.RoomId = r.Id
                JOIN {$this->wpdb->prefix}myvh_venues v ON r.VenueId = v.Id
                WHERE rp.IsActive = 1
                ORDER BY rp.StartDate DESC";

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        if ($results === null) {
            error_log('MYVH Recurring_Pattern Repository Error (get_all_active_with_bookings): ' . $this->wpdb->last_error);
        }

        return $results;
    }

    /**
     * Get recurring pattern by parent booking ID
     *
     * @param int $booking_id The parent booking ID
     * @return array|null The record data or null if not found
     */
    public function get_by_parent_booking($booking_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE ParentBookingId = %d",
            $booking_id
        );
        
        $result = $this->wpdb->get_row($sql, ARRAY_A);
        
        if ($result === null && $this->wpdb->last_error) {
            error_log('MYVH Recurring_Pattern Repository Error (get_by_parent_booking): ' . $this->wpdb->last_error);
        }
        
        return $result;
    }

    /**
     * Get all active patterns that need processing
     *
     * @return array|null Array of records or null on failure
     */
    public function get_patterns_needing_processing() {
        $today = date('Y-m-d');
        
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name 
             WHERE IsActive = 1 
             AND (EndDate IS NULL OR EndDate >= %s)
             AND (MaxOccurrences IS NULL OR OccurrenceCount < MaxOccurrences)
             ORDER BY StartDate",
            $today
        );
        
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        if ($results === null) {
            error_log('MYVH Recurring_Pattern Repository Error (get_patterns_needing_processing): ' . $this->wpdb->last_error);
        }
        
        return $results;
    }

    /**
     * Increment occurrence count
     *
     * @param int $id The pattern ID
     * @return bool True on success, false on failure
     */
    public function increment_occurrence_count($id) {
        $sql = $this->wpdb->prepare(
            "UPDATE $this->table_name SET OccurrenceCount = OccurrenceCount + 1 WHERE Id = %d",
            $id
        );
        
        $result = $this->wpdb->query($sql);
        
        if ($result === false) {
            error_log('MYVH Recurring_Pattern Repository Error (increment_occurrence_count): ' . $this->wpdb->last_error);
            return false;
        }
        
        return true;
    }

    /**
     * Deactivate pattern
     *
     * @param int $id The pattern ID
     * @return bool True on success, false on failure
     */
    public function deactivate($id) {
        return $this->update(array('IsActive' => 0), array('Id' => $id));
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
