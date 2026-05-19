<?php
namespace MYVH\Rooms;

use MYVH\Core\Support\RepositoryBase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RoomRepository extends RepositoryBase {

    private static bool $colour_column_checked = false;
    private LoggerInterface $logger;

    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb, ?LoggerInterface $logger = null ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_rooms';
        $this->logger = $logger ?? new NullLogger();
    }

    public function last_error(): string {
        return (string) ($this->wpdb->last_error ?? '');
    }

    // Custom method preserved
    public function get_all_with_venues(): array {
        $sql = "SELECT r.*, v.Id as VenueId, v.Name as VenueName FROM $this->table_name r\n                JOIN {$this->wpdb->prefix}myvh_venues v ON r.VenueId = v.Id\n                ORDER BY v.Name, r.Name";

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        if ($results === null) {
            $this->logger->error('Room repository query failed', [
                'method' => 'get_all_with_venues',
                'db_error' => (string) $this->wpdb->last_error,
            ]);
        }
        return $results;
    }

    /**
     * Get all public rooms with venue information.
     *
        * @return array<int, array<string, mixed>> List of public rooms with venue details.
     */
    public function get_public_with_venues(): array {
        $sql = "SELECT r.*, v.Id as VenueId, v.Name as VenueName FROM $this->table_name r\n                JOIN {$this->wpdb->prefix}myvh_venues v ON r.VenueId = v.Id\n                WHERE r.IsPublic = 1\n                ORDER BY v.Name, r.Name";

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        if ($results === null) {
            $this->logger->error('Room repository query failed', [
                'method' => 'get_public_with_venues',
                'db_error' => (string) $this->wpdb->last_error,
            ]);
        }
        return $results;
    }

    /**
     * Get all rooms for a specific venue, filtered by visibility.
     *
     * @param int  $venue_id The venue ID to filter by.
     * @param bool $public_only If true, only return public rooms. If false, return all rooms.
     * @return array<int, array<string, mixed>> List of rooms for the venue.
     */
    public function get_by_venue(int $venue_id, bool $public_only = false): array {
        $sql = "SELECT * FROM $this->table_name WHERE VenueId = %d";
        if ($public_only) {
            $sql .= " AND IsPublic = 1";
        }
        $sql .= " ORDER BY Name";

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $venue_id),
            ARRAY_A
        );
        if ($results === null) {
            $this->logger->error('Room repository query failed', [
                'method' => 'get_by_venue',
                'venue_id' => $venue_id,
                'db_error' => (string) $this->wpdb->last_error,
            ]);
        }
        return $results;
    }

    /**
     * Get room IDs that are public and have active rates (for booking/calendar display).
     *
     * @param array<int, int|string> $room_ids Optional. If provided, filter results to these IDs.
     * @return array<int, int|string> List of public room IDs with active rates.
     */
    public function get_public_room_ids(array $room_ids = []): array {
        $sql = "SELECT DISTINCT Id FROM $this->table_name WHERE IsPublic = 1";

        if (!empty($room_ids)) {
            $placeholders = implode(',', array_map('intval', $room_ids));
            $sql .= " AND Id IN ({$placeholders})";
        }

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        if ($results === null) {
            $this->logger->error('Room repository query failed', [
                'method' => 'get_public_room_ids',
                'db_error' => (string) $this->wpdb->last_error,
            ]);
        }
        return array_map(
            static fn(array $row) => $row['Id'],
            $results ?? []
        );
    }
}