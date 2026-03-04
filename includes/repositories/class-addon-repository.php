<?php
/**
 * Repository class for myvh_addons table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MYVH_Addon_Repository {
    
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
        $this->table_name = $wpdb->prefix . 'myvh_addons';
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
            error_log('MYVH Addon Repository Error (create): ' . $this->wpdb->last_error);
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
            'orderby' => 'DisplayOrder',
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
            error_log('MYVH Addon Repository Error (get_all): ' . $this->wpdb->last_error);
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
            error_log('MYVH Addon Repository Error (get_by_id): ' . $this->wpdb->last_error);
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
            error_log('MYVH Addon Repository Error (update): ' . $this->wpdb->last_error);
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
            error_log('MYVH Addon Repository Error (delete): ' . $this->wpdb->last_error);
            return false;
        }
        
        return true;
    }

    /**
     * Get all addons with their related entities
     *
     * @return array|null Array of records or null on failure
     */
    public function get_all_with_relations() {
        $sql = "SELECT 
                    a.*,
                    cg.Name as CustomerGroupName,
                    r.Name as RoomName,
                    v.Name as VenueName
                FROM $this->table_name a
                LEFT JOIN {$this->wpdb->prefix}myvh_customer_groups cg ON a.CustomerGroupId = cg.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_rooms r ON a.RoomId = r.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_venues v ON a.VenueId = v.Id
                ORDER BY a.DisplayOrder, a.Name";

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        if ($results === null) {
            error_log('MYVH Addon Repository Error (get_all_with_relations): ' . $this->wpdb->last_error);
        }

        return $results;
    }

    /**
     * Get addons by room ID
     *
     * @param int $room_id The room ID
     * @return array|null Array of records or null on failure
     */
    public function get_by_room($room_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE (RoomId = %d OR RoomId IS NULL) AND IsActive = 1 ORDER BY DisplayOrder",
            $room_id
        );
        
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        if ($results === null) {
            error_log('MYVH Addon Repository Error (get_by_room): ' . $this->wpdb->last_error);
        }
        
        return $results;
    }

    /**
     * Get addons by venue ID
     *
     * @param int $venue_id The venue ID
     * @return array|null Array of records or null on failure
     */
    public function get_by_venue($venue_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE (VenueId = %d OR VenueId IS NULL) AND IsActive = 1 ORDER BY DisplayOrder",
            $venue_id
        );
        
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        if ($results === null) {
            error_log('MYVH Addon Repository Error (get_by_venue): ' . $this->wpdb->last_error);
        }
        
        return $results;
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

// Initialize global repository instance
global $myvh_addon_repo;
$myvh_addon_repo = new MYVH_Addon_Repository();
