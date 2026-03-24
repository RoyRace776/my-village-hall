<?php
/**
 * Repository class for myvh_invoice_items table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MYVH_Invoice_Item_Repository extends MYVH_Repository_Base{

    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_invoice_items';
        $this->ensure_booking_id_column();
    }

    private function ensure_booking_id_column(): void {
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare( "SHOW COLUMNS FROM {$this->table_name} LIKE %s", 'BookingId' )
        );
        if ( ! $exists ) {
            $this->wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN BookingId INT UNSIGNED DEFAULT NULL AFTER InvoiceId" );
            $this->wpdb->query( "ALTER TABLE {$this->table_name} ADD INDEX idx_booking (BookingId)" );
        }
    }

}
