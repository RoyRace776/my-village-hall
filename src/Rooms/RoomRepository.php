<?php
namespace MYVH\Rooms;

use MYVH\Core\Support\RepositoryBase;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RoomRepository extends RepositoryBase {

    private static bool $colour_column_checked = false;

    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_rooms';
    }

    public function ensure_colour_column_exists(): void {
        if (self::$colour_column_checked) {
            return;
        }

        $exists = $this->wpdb->get_var("SHOW COLUMNS FROM {$this->table_name} LIKE 'Colour'");

        if (!$exists) {
            $this->wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN Colour VARCHAR(7) NULL AFTER Name");
        }

        self::$colour_column_checked = true;
    }

    public function last_error(): string {
        return (string) ($this->wpdb->last_error ?? '');
    }

    // Custom method preserved
    public function get_all_with_venues(): array {
        $sql = "SELECT r.*, v.Id as VenueId, v.Name as VenueName FROM $this->table_name r\n                JOIN {$this->wpdb->prefix}myvh_venues v ON r.VenueId = v.Id\n                ORDER BY v.Name, r.Name";

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        if ($results === null) {
            error_log('MYVH Room Repository Error (get_all_with_venues): ' . $this->wpdb->last_error);
        }
        return $results;
    }

    /**
     * Get all public rooms with venue information.
     *
     * @return array List of public rooms with venue details.
     */
    public function get_public_with_venues(): array {
        $sql = "SELECT r.*, v.Id as VenueId, v.Name as VenueName FROM $this->table_name r\n                JOIN {$this->wpdb->prefix}myvh_venues v ON r.VenueId = v.Id\n                WHERE r.IsPublic = 1\n                ORDER BY v.Name, r.Name";

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        if ($results === null) {
            error_log('MYVH Room Repository Error (get_public_with_venues): ' . $this->wpdb->last_error);
        }
        return $results;
    }

    /**
     * Get all rooms for a specific venue, filtered by visibility.
     *
     * @param int  $venue_id The venue ID to filter by.
     * @param bool $public_only If true, only return public rooms. If false, return all rooms.
     * @return array List of rooms for the venue.
     */
    public function get_by_venue($venue_id, $public_only = false): array {
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
            error_log('MYVH Room Repository Error (get_by_venue): ' . $this->wpdb->last_error);
        }
        return $results;
    }

    /**
     * Get room IDs that are public and have active rates (for booking/calendar display).
     *
     * @param array $room_ids Optional. If provided, filter results to these IDs.
     * @return array List of public room IDs with active rates.
     */
    public function get_public_room_ids($room_ids = []): array {
        $sql = "SELECT DISTINCT Id FROM $this->table_name WHERE IsPublic = 1";

        if (!empty($room_ids)) {
            $placeholders = implode(',', array_map('intval', $room_ids));
            $sql .= " AND Id IN ({$placeholders})";
        }

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        if ($results === null) {
            error_log('MYVH Room Repository Error (get_public_room_ids): ' . $this->wpdb->last_error);
        }
        return array_map(function($row) { return $row['Id']; }, $results ?? []);
    }
}