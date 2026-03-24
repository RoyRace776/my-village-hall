<?php
/**
 * Repository class for myvh_organisations table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class MYVH_Organisation_Repository extends MYVH_Repository_Base{

    /** @var string */
    private $table;

    /** @var string */
    private $types_table;

    public function __construct( \wpdb $wpdb ) {
        $this->table       = $wpdb->prefix . 'myvh_organisations';
        $this->types_table = $wpdb->prefix . 'myvh_organisation_types';

        $this->ensure_is_default_column();
        $this->ensure_default_public_column();
        $this->ensure_website_url_column();
        $this->ensure_invoice_organisation_bookings_column();
        $this->ensure_billing_columns();
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function get_default() {
        $sql = "SELECT * FROM {$this->table} WHERE IsDefault = 1 ORDER BY Id ASC LIMIT 1";
        return $this->wpdb->get_row( $sql, ARRAY_A );
    }

    /**
     * Get all organisations joined with their type name.
     */
    public function get_all_with_type( array $args = [] ) {
        $defaults = [ 'orderby' => 'o.Name', 'order' => 'ASC', 'active_only' => false ];
        $args     = wp_parse_args( $args, $defaults );

        $where = $args['active_only'] ? 'WHERE o.IsActive = 1' : '';
        $order = esc_sql( $args['orderby'] ) . ' ' . ( strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC' );

        $sql = "SELECT o.*, ot.Name AS OrganisationTypeName
                FROM {$this->table} o
                LEFT JOIN {$this->types_table} ot ON o.OrganisationTypeId = ot.Id
                {$where}
                ORDER BY {$order}";

        return $this->wpdb->get_results( $sql, ARRAY_A ) ?? [];
    }

    public function count_all(): int {
        return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
    }

    public function clear_default_except( ?int $organisation_id = null ): bool {
        $sql = "UPDATE {$this->table} SET IsDefault = 0";

        if ( $organisation_id ) {
            $sql .= $this->wpdb->prepare( ' WHERE Id != %d', $organisation_id );
        }

        $result = $this->wpdb->query( $sql );
        return $result !== false;
    }

    public function has_default(): bool {
        $count = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE IsDefault = 1" );
        return $count > 0;
    }

    private function ensure_is_default_column(): void {
        $exists = $this->wpdb->get_var( "SHOW COLUMNS FROM {$this->table} LIKE 'IsDefault'" );

        if ( $exists ) {
            return;
        }

        $this->wpdb->query( "ALTER TABLE {$this->table} ADD COLUMN IsDefault TINYINT(1) NOT NULL DEFAULT 0" );
        $this->wpdb->query( "ALTER TABLE {$this->table} ADD INDEX idx_default (IsDefault)" );
    }

    private function ensure_default_public_column(): void {
        $exists = $this->wpdb->get_var( "SHOW COLUMNS FROM {$this->table} LIKE 'DefaultPublic'" );

        if ( $exists ) {
            return;
        }

        $this->wpdb->query( "ALTER TABLE {$this->table} ADD COLUMN DefaultPublic TINYINT(1) NOT NULL DEFAULT 0" );
        $this->wpdb->query( "ALTER TABLE {$this->table} ADD INDEX idx_default_public (DefaultPublic)" );
    }

    private function ensure_website_url_column(): void {
        $exists = $this->wpdb->get_var( "SHOW COLUMNS FROM {$this->table} LIKE 'WebsiteUrl'" );

        if ( $exists ) {
            return;
        }

        $this->wpdb->query( "ALTER TABLE {$this->table} ADD COLUMN WebsiteUrl VARCHAR(255) NULL" );
    }

    private function ensure_invoice_organisation_bookings_column(): void {
        $exists = $this->wpdb->get_var( "SHOW COLUMNS FROM {$this->table} LIKE 'InvoiceOrganisationBookings'" );

        if ( $exists ) {
            return;
        }

        $this->wpdb->query( "ALTER TABLE {$this->table} ADD COLUMN InvoiceOrganisationBookings TINYINT(1) NOT NULL DEFAULT 0 AFTER WebsiteUrl" );
        $this->wpdb->query( "ALTER TABLE {$this->table} ADD INDEX idx_invoice_org (InvoiceOrganisationBookings)" );
    }

    private function ensure_billing_columns(): void {
        $columns = [
            'BillingContactName'  => "ALTER TABLE {$this->table} ADD COLUMN BillingContactName VARCHAR(150) NULL",
            'BillingEmail'        => "ALTER TABLE {$this->table} ADD COLUMN BillingEmail VARCHAR(150) NULL",
            'BillingAddressLine1' => "ALTER TABLE {$this->table} ADD COLUMN BillingAddressLine1 VARCHAR(255) NULL",
            'BillingAddressLine2' => "ALTER TABLE {$this->table} ADD COLUMN BillingAddressLine2 VARCHAR(255) NULL",
            'BillingTownCity'     => "ALTER TABLE {$this->table} ADD COLUMN BillingTownCity VARCHAR(120) NULL",
            'BillingPostcode'     => "ALTER TABLE {$this->table} ADD COLUMN BillingPostcode VARCHAR(30) NULL",
            'BillingReference'    => "ALTER TABLE {$this->table} ADD COLUMN BillingReference VARCHAR(100) NULL",
        ];

        foreach ( $columns as $column_name => $sql ) {
            $exists = $this->wpdb->get_var( $this->wpdb->prepare( "SHOW COLUMNS FROM {$this->table} LIKE %s", $column_name ) );
            if ( !$exists ) {
                $this->wpdb->query( $sql );
            }
        }
    }
}
