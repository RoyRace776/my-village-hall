<?php

namespace MYVH\Bookings;

use MYVH\Core\Support\RepositoryBase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use wpdb;

/**
 * Repository class for myvh_booking_addons table
 *
 * @package MYVH
 */
class BookingAddonRepository extends RepositoryBase
{
    private LoggerInterface $logger;

    /**
     * Constructor
     */
    public function __construct(wpdb $wpdb, ?LoggerInterface $logger = null)
    {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_booking_addons';
        $this->logger = $logger ?? new NullLogger();
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
            $this->logger->error('Booking addon repository query failed', [
                'method' => 'get_by_booking_id',
                'booking_id' => (int) $booking_id,
                'db_error' => (string) $this->wpdb->last_error,
            ]);
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
            $this->logger->error('Booking addon repository delete failed', [
                'method' => 'delete_by_booking_id',
                'booking_id' => (int) $booking_id,
                'db_error' => (string) $this->wpdb->last_error,
            ]);
            return false;
        }

        return true;
    }
}
