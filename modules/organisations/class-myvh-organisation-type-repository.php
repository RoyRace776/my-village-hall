<?php
/**
 * Repository class for myvh_organisation_types table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class MYVH_Organisation_Type_Repository extends MYVH_Repository_Base{

    /** @var string */
    private $table;

    public function __construct( \wpdb $wpdb ) {
        $this->wpdb  = $wpdb;
        $this->table = $wpdb->prefix . 'myvh_organisation_types';
    }

}
