<?php
/**
 * Repository class for myvh_rooms table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RoomRepository extends RepositoryBase {

    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_rooms';
    }
    // Custom method preserved
    public function get_all_with_venues(): array {
        $sql = "SELECT r.*, v.Id as VenueId, v.Name as VenueName FROM $this->table_name r
                JOIN {$this->wpdb->prefix}myvh_venues v ON r.VenueId = v.Id
                ORDER BY v.Name, r.Name";

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        if ($results === null) {
            error_log('MYVH Room Repository Error (get_all_with_venues): ' . $this->wpdb->last_error);
        }
        return $results;
    }
}
