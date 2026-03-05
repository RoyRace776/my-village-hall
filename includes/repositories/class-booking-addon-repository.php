<?php
/**
 * Repository class for myvh_booking_addons table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MYVH_Booking_Addon_Repository {
    
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
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_booking_addons';
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
            error_log('MYVH Booking_Addon Repository Error (create): ' . $this->wpdb->last_error);
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
            error_log('MYVH Booking_Addon Repository Error (get_all): ' . $this->wpdb->last_error);
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
            error_log('MYVH Booking_Addon Repository Error (get_by_id): ' . $this->wpdb->last_error);
        }
        
        return $result;
    }
    
    /**
     * Update a record
     *
     * @param array $data Associative array of column => value pairs to update
     * @param array $where Associative array of WHERE conditions e.g. ['Id' => 5]
     * @return bool True on success, false on failure
     */
    public function update($data, $where) {
        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            $where,
            $this->get_format($data),
            array('%d')
        );

        if ($result === false) {
            error_log('MYVH Booking_Addon Repository Error (update): ' . $this->wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Get all addon records for a given booking
     *
     * @param int $booking_id
     * @return array|null
     */
    public function get_by_booking_id($booking_id) {
        $sql = $this->wpdb->prepare(
            "SELECT ba.*, a.Name as AddonName, a.ChargeType
             FROM {$this->table_name} ba
             LEFT JOIN {$this->wpdb->prefix}myvh_addons a ON ba.AddonId = a.Id
             WHERE ba.BookingId = %d
             ORDER BY ba.Id",
            $booking_id
        );

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if ($results === null) {
            error_log('MYVH Booking_Addon Repository Error (get_by_booking_id): ' . $this->wpdb->last_error);
        }

        return $results;
    }

    /**
     * Delete all addon records for a given booking
     *
     * @param int $booking_id
     * @return bool
     */
    public function delete_by_booking_id($booking_id) {
        $result = $this->wpdb->delete(
            $this->table_name,
            array('BookingId' => $booking_id),
            array('%d')
        );

        if ($result === false) {
            error_log('MYVH Booking_Addon Repository Error (delete_by_booking_id): ' . $this->wpdb->last_error);
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
            error_log('MYVH Booking_Addon Repository Error (delete): ' . $this->wpdb->last_error);
            return false;
        }
        
        return true;
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
