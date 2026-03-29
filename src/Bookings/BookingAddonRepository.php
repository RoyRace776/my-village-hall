<?php

namespace MYVH\Bookings;

use RepositoryBase;
use wpdb;

/**
 * Repository class for myvh_booking_addons table
 *
 * @package MYVH
 */
class BookingAddonRepository extends RepositoryBase
{
    /**
     * Constructor
     */
    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_booking_addons';
    }

    /**
     * Get all addon records for a given booking
     *
     * @param int $booking_id
     * @return array|null
     */
    public function get_by_booking_id($booking_id): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT ba.*, a.Name as AddonName, a.ChargeType
             FROM {$this->table_name} ba
             LEFT JOIN {$this->wpdb->prefix}myvh_addons a ON ba.AddonId = a.Id
             WHERE ba.BookingId = %d
             ORDER BY ba.Id",
            $booking_id
        );

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if ($results === null) {
            error_log('MYVH Booking_Addon Repository Error (get_by_booking_id): ' . $this->wpdb->last_error);
        }

        return $results;
    }

    /**
     * Delete all addon records for a given booking
     *
     * @param int $booking_id
     * @return bool
     */
    public function delete_by_booking_id($booking_id): bool
    {
        $result = $this->wpdb->delete(
            $this->table_name,
            array('BookingId' => $booking_id),
            array('%d')
        );

        if ($result === false) {
            error_log('MYVH Booking_Addon Repository Error (delete_by_booking_id): ' . $this->wpdb->last_error);
            return false;
        }

        return true;
    }
}
