<?php
/**
 * Repository class for myvh_booking_discounts table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class BookingDiscountRepository extends RepositoryBase {

    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_booking_discounts';
    }

}
