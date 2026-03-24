<?php
/**
 * Repository class for myvh_organisation_members table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class MYVH_Organisation_Member_Repository extends MYVH_Repository_Base{

    /** @var string */
    private $table;

    public function __construct( \wpdb $wpdb ) {
        $this->table = $wpdb->prefix . 'myvh_organisation_members';
        $this->wpdb = $wpdb;

        $this->ensure_admin_column();
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function get_by_organisation_and_customer( int $org_id, int $customer_id ) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE OrganisationId = %d AND CustomerId = %d",
            $org_id,
            $customer_id
        );

        return $this->wpdb->get_row( $sql, ARRAY_A );
    }

    /**
     * Get all members for a given organisation, joined with WP user display names.
     */
    public function get_by_organisation( int $org_id ): array {
        $sql = $this->wpdb->prepare(
            "SELECT om.*, u.Name AS Name, u.Email AS Email
             FROM {$this->table} om
             LEFT JOIN {$this->wpdb->prefix}myvh_customers u ON om.CustomerId = u.Id
             WHERE om.OrganisationId = %d
             ORDER BY u.Name",
            $org_id
        );

        return $this->wpdb->get_results( $sql, ARRAY_A ) ?? [];
    }

    /**
     * Get all organisations a given user belongs to.
     */
    public function get_by_user( int $user_id ): array {
        $sql = $this->wpdb->prepare(
            "SELECT om.*, o.Name AS OrganisationName
             FROM {$this->table} om
             LEFT JOIN {$this->wpdb->prefix}myvh_organisations o ON om.OrganisationId = o.Id
             WHERE om.CustomerId = %d",
            $user_id
        );

        return $this->wpdb->get_results( $sql, ARRAY_A ) ?? [];
    }

    public function get_admin_organisations_for_customer( int $customer_id ): array {
        $sql = $this->wpdb->prepare(
            "SELECT o.*
             FROM {$this->table} om
             JOIN {$this->wpdb->prefix}myvh_organisations o ON om.OrganisationId = o.Id
             WHERE om.CustomerId = %d
               AND om.IsOrganisationAdmin = 1
             ORDER BY o.Name",
            $customer_id
        );

        return $this->wpdb->get_results( $sql, ARRAY_A ) ?? [];
    }

    public function exists( int $org_id, int $user_id ): bool {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE OrganisationId = %d AND CustomerId = %d",
            $org_id, $user_id
        );

        return (int) $this->wpdb->get_var( $sql ) > 0;
    }

    public function is_customer_admin( int $org_id, int $customer_id ): bool {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$this->table}
             WHERE OrganisationId = %d
               AND CustomerId = %d
               AND IsOrganisationAdmin = 1",
            $org_id,
            $customer_id
        );

        return (int) $this->wpdb->get_var( $sql ) > 0;
    }

    public function count_admins_for_organisation( int $org_id ): int {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$this->table}
             WHERE OrganisationId = %d
               AND IsOrganisationAdmin = 1",
            $org_id
        );

        return (int) $this->wpdb->get_var( $sql );
    }

    public function update_admin_status( int $member_id, bool $is_admin ) {
        $result = $this->wpdb->update(
            $this->table,
            [ 'IsOrganisationAdmin' => $is_admin ? 1 : 0 ],
            [ 'Id' => $member_id ],
            [ '%d' ],
            [ '%d' ]
        );

        if ( $result === false ) {
            error_log( 'MYVH Organisation_Member Repository Error (update_admin_status): ' . $this->wpdb->last_error );
            return false;
        }

        return true;
    }

    public function delete_by_organisation( int $org_id ): bool {
        $result = $this->wpdb->delete( $this->table, [ 'OrganisationId' => $org_id ], [ '%d' ] );

        if ( $result === false ) {
            error_log( 'MYVH Organisation_Member Repository Error (delete_by_organisation): ' . $this->wpdb->last_error );
            return false;
        }

        return true;
    }

    private function ensure_admin_column(): void {
        $exists = $this->wpdb->get_var( "SHOW COLUMNS FROM {$this->table} LIKE 'IsOrganisationAdmin'" );

        if ( $exists ) {
            return;
        }

        $this->wpdb->query( "ALTER TABLE {$this->table} ADD COLUMN IsOrganisationAdmin TINYINT(1) NOT NULL DEFAULT 0" );
    }
}