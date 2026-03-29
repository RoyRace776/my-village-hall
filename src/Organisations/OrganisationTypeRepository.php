<?php
namespace MYVH\Organisations;

use MYVH\Core\Support\RepositoryBase;
use wpdb;

if (!defined('ABSPATH')) exit;

class OrganisationTypeRepository extends RepositoryBase {
    public function __construct(wpdb $wpdb) {
        $this->wpdb  = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_organisation_types';
    }
}
