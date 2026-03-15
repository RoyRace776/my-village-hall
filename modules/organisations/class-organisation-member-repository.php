<?php
/**
 * Repository class for myvh_organisation_members table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class MYVH_Organisation_Member_Repository {

    /** @var wpdb */
    private $wpdb;

    /** @var string */
    private $table;

    public function __construct( \wpdb $wpdb ) {
        $this->wpdb  = $wpdb;
        $this->table = $wpdb->prefix . 'myvh_organisation_members';
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function create( array $data ) {
        $result = $this->wpdb->insert( $this->table, $data, $this->get_format( $data ) );

        if ( $result === false ) {
            error_log( 'MYVH Organisation_Member Repository Error (create): ' . $this->wpdb->last_error );
            return false;
        }

        return $this->wpdb->insert_id;
    }

    public function get_by_id( int $id ) {
        $sql = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE Id = %d", $id );
        return $this->wpdb->get_row( $sql, ARRAY_A );
    }

    /**
     * Get all members for a given organisation, joined with WP user display names.
     */
    public function get_by_organisation( int $org_id ): array {
        $sql = $this->wpdb->prepare(
            "SELECT om.*, u.display_name AS UserDisplayName, u.user_email AS UserEmail
             FROM {$this->table} om
             LEFT JOIN {$this->wpdb->users} u ON om.CustomerId = u.ID
             WHERE om.OrganisationId = %d
             ORDER BY u.display_name",
            $org_id
        );

        return $this->wpdb->get_results( $sql, ARRAY_A ) ?? [];
    }

    /**
     * Get all organisations a given WP user belongs to.
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

    public function exists( int $org_id, int $user_id ): bool {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE OrganisationId = %d AND CustomerId = %d",
            $org_id, $user_id
        );

        return (int) $this->wpdb->get_var( $sql ) > 0;
    }

    public function delete( int $id ) {
        $result = $this->wpdb->delete( $this->table, [ 'Id' => $id ], [ '%d' ] );

        if ( $result === false ) {
            error_log( 'MYVH Organisation_Member Repository Error (delete): ' . $this->wpdb->last_error );
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

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function get_format( array $data ): array {
        return array_map( function ( $v ) {
            if ( is_int( $v ) )   return '%d';
            if ( is_float( $v ) ) return '%f';
            return '%s';
        }, $data );
    }
}
