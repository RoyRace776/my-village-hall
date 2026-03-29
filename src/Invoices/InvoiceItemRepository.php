<?php
namespace MYVH\Invoices;

use MYVH\Core\Support\RepositoryBase;

/**
 * Repository class for myvh_invoice_items table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class InvoiceItemRepository extends RepositoryBase{

    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_invoice_items';
    }

}
