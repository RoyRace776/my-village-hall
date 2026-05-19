<?php
/**
 * Repository class for myvh_recurring_patterns table
 *
 * @package MYVH
 */
namespace MYVH\Bookings;

use MYVH\Core\Support\RepositoryBase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RecurringPatternRepository extends RepositoryBase {

    private LoggerInterface $logger;


    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb, ?LoggerInterface $logger = null ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_recurring_patterns';
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Get all active recurring patterns with booking details
     *
     * @return array|null Array of records or null on failure
     */
    public function get_all_active_with_bookings(): ?array {
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
            $this->logger->error('Recurring pattern repository query failed', [
                'method' => 'get_all_active_with_bookings',
                'db_error' => (string) $this->wpdb->last_error,
            ]);
        }

        return $results;
    }

    /**
     * Get recurring pattern by parent booking ID
     *
     * @param int $booking_id The parent booking ID
     * @return array|null The record data or null if not found
     */
    public function get_by_parent_booking($booking_id): ?array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE ParentBookingId = %d",
            $booking_id
        );

        $result = $this->wpdb->get_row($sql, ARRAY_A);

        if ($result === null && $this->wpdb->last_error) {
            $this->logger->error('Recurring pattern repository query failed', [
                'method' => 'get_by_parent_booking',
                'booking_id' => (int) $booking_id,
                'db_error' => (string) $this->wpdb->last_error,
            ]);
        }

        return $result;
    }

    /**
     * Get all active patterns that need processing
     *
     * @return array|null Array of records or null on failure
     */
    public function get_patterns_needing_processing(): ?array {
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
            $this->logger->error('Recurring pattern repository query failed', [
                'method' => 'get_patterns_needing_processing',
                'db_error' => (string) $this->wpdb->last_error,
            ]);
        }

        return $results;
    }

    /**
     * Increment occurrence count
     *
     * @param int $id The pattern ID
     * @return bool True on success, false on failure
     */
    public function increment_occurrence_count($id): bool {
        $sql = $this->wpdb->prepare(
            "UPDATE $this->table_name SET OccurrenceCount = OccurrenceCount + 1 WHERE Id = %d",
            $id
        );

        $result = $this->wpdb->query($sql);

        if ($result === false) {
            $this->logger->error('Recurring pattern repository update failed', [
                'method' => 'increment_occurrence_count',
                'pattern_id' => (int) $id,
                'db_error' => (string) $this->wpdb->last_error,
            ]);
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
    public function deactivate($id): bool {
        return $this->update(array('IsActive' => 0), array('Id' => $id));
    }
}

