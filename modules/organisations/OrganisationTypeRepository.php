<?php
/**
 * Repository class for myvh_organisation_types table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class OrganisationTypeRepository extends RepositoryBase{

    public function __construct( \wpdb $wpdb ) {
        $this->wpdb  = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_organisation_types';
    }

}
