<?php

namespace MYVH\Bookings;

use MYVH\Core\Support\RepositoryBase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use wpdb;

/**
 * Repository class for myvh_booking_charges table
 *
 * @package MYVH
 */
class BookingChargeRepository extends RepositoryBase
{
    private LoggerInterface $logger;

    /**
     * Constructor
     */
    public function __construct(wpdb $wpdb, ?LoggerInterface $logger = null)
    {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_booking_charges';
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Get all charge rows for a booking
     *
     * @param int $booking_id Booking ID
     * @return array
     */
    public function get_by_booking_id($booking_id): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE BookingId = %d ORDER BY Id ASC",
            $booking_id
        );

        $result = $this->wpdb->get_results($sql, ARRAY_A);

        if ($result === null && $this->wpdb->last_error) {
            $this->logger->error('Booking charge repository query failed', [
                'method' => 'get_by_booking_id',
                'booking_id' => (int) $booking_id,
                'db_error' => (string) $this->wpdb->last_error,
            ]);
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
    public function delete_by_booking_id($booking_id): bool
    {
        $result = $this->wpdb->delete(
            $this->table_name,
            array('BookingId' => \intval($booking_id)),
            array('%d')
        );

        if ($result === false) {
            $this->logger->error('Booking charge repository delete failed', [
                'method' => 'delete_by_booking_id',
                'booking_id' => (int) $booking_id,
                'db_error' => (string) $this->wpdb->last_error,
            ]);
            return false;
        }

        return true;
    }
}
