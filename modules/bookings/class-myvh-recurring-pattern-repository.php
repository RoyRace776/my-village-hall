<?php
/**
 * Repository class for myvh_recurring_patterns table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MYVH_Recurring_Pattern_Repository extends MYVH_Repository_Base {


    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_recurring_patterns';
    }

    /**
     * Get all active recurring patterns with booking details
     *
     * @return array|null Array of records or null on failure
     */
    public function get_all_active_with_bookings() {
        $sql = "SELECT
                    rp.*,
                    b.CustomerId,
                    b.RoomId,
                    b.Description,
                    c.Name as CustomerName,
                    r.Name as RoomName,
                    v.Name as VenueName
                FROM $this->table_name rp
                JOIN {$this->wpdb->prefix}myvh_bookings b ON rp.ParentBookingId = b.Id
                JOIN {$this->wpdb->prefix}myvh_customers c ON b.CustomerId = c.Id
                JOIN {$this->wpdb->prefix}myvh_rooms r ON b.RoomId = r.Id
                JOIN {$this->wpdb->prefix}myvh_venues v ON r.VenueId = v.Id
                WHERE rp.IsActive = 1
                ORDER BY rp.StartDate DESC";

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if ($results === null) {
            error_log('MYVH Recurring_Pattern Repository Error (get_all_active_with_bookings): ' . $this->wpdb->last_error);
        }

        return $results;
    }

    /**
     * Get recurring pattern by parent booking ID
     *
     * @param int $booking_id The parent booking ID
     * @return array|null The record data or null if not found
     */
    public function get_by_parent_booking($booking_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE ParentBookingId = %d",
            $booking_id
        );

        $result = $this->wpdb->get_row($sql, ARRAY_A);

        if ($result === null && $this->wpdb->last_error) {
            error_log('MYVH Recurring_Pattern Repository Error (get_by_parent_booking): ' . $this->wpdb->last_error);
        }

        return $result;
    }

    /**
     * Get all active patterns that need processing
     *
     * @return array|null Array of records or null on failure
     */
    public function get_patterns_needing_processing() {
        $today = date('Y-m-d');

        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name
             WHERE IsActive = 1
             AND (EndDate IS NULL OR EndDate >= %s)
             AND (MaxOccurrences IS NULL OR OccurrenceCount < MaxOccurrences)
             ORDER BY StartDate",
            $today
        );

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if ($results === null) {
            error_log('MYVH Recurring_Pattern Repository Error (get_patterns_needing_processing): ' . $this->wpdb->last_error);
        }

        return $results;
    }

    /**
     * Increment occurrence count
     *
     * @param int $id The pattern ID
     * @return bool True on success, false on failure
     */
    public function increment_occurrence_count($id) {
        $sql = $this->wpdb->prepare(
            "UPDATE $this->table_name SET OccurrenceCount = OccurrenceCount + 1 WHERE Id = %d",
            $id
        );

        $result = $this->wpdb->query($sql);

        if ($result === false) {
            error_log('MYVH Recurring_Pattern Repository Error (increment_occurrence_count): ' . $this->wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Deactivate pattern
     *
     * @param int $id The pattern ID
     * @return bool True on success, false on failure
     */
    public function deactivate($id) {
        return $this->update(array('IsActive' => 0), array('Id' => $id));
    }
}
