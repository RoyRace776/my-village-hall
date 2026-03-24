<?php
/**
 * Repository class for myvh_venues table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MYVH_Venue_Repository extends MYVH_Repository_Base {
    // Add any custom methods below as needed

    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_venues';
    }
}
