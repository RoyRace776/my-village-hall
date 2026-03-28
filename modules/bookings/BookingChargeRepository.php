<?php
/**
 * Repository class for myvh_booking_charges table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class BookingChargeRepository extends RepositoryBase {


    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_booking_charges';
    }

    /**
     * Get all charge rows for a booking
     *
     * @param int $booking_id Booking ID
     * @return array
     */
    public function get_by_booking_id($booking_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE BookingId = %d ORDER BY Id ASC",
            $booking_id
        );

        $result = $this->wpdb->get_results($sql, ARRAY_A);

        if ($result === null && $this->wpdb->last_error) {
            error_log('MYVH Booking_Charge Repository Error (get_by_booking_id): ' . $this->wpdb->last_error);
            return [];
        }

        return $result ?? [];
    }


    /**
     * Delete all charge rows for a booking
     *
     * @param int $booking_id Booking ID
     * @return bool True on success, false on failure
     */
    public function delete_by_booking_id($booking_id): bool {
        $result = $this->wpdb->delete(
            $this->table_name,
            array('BookingId' => intval($booking_id)),
            array('%d')
        );

        if ($result === false) {
            error_log('MYVH Booking_Charge Repository Error (delete_by_booking_id): ' . $this->wpdb->last_error);
            return false;
        }

        return true;
    }

}
