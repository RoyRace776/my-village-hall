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
}