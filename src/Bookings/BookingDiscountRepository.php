<?php

namespace MYVH\Bookings;

use RepositoryBase;
use wpdb;

/**
 * Repository class for myvh_booking_discounts table
 *
 * @package MYVH
 */
class BookingDiscountRepository extends RepositoryBase
{
    /**
     * Constructor
     */
    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_booking_discounts';
    }
}
