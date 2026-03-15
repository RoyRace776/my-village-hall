<?php
/**
 * Repository class for myvh_organisations table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class MYVH_Organisation_Repository {

    /** @var wpdb */
    private $wpdb;

    /** @var string */
    private $table;

    /** @var string */
    private $types_table;

    public function __construct( \wpdb $wpdb ) {
        $this->wpdb        = $wpdb;
        $this->table       = $wpdb->prefix . 'myvh_organisations';
        $this->types_table = $wpdb->prefix . 'myvh_organisation_types';
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function create( array $data ) {
        $result = $this->wpdb->insert( $this->table, $data, $this->get_format( $data ) );

        if ( $result === false ) {
            error_log( 'MYVH Organisation Repository Error (create): ' . $this->wpdb->last_error );
            return false;
        }

        return $this->wpdb->insert_id;
    }

    public function get_by_id( int $id ) {
        $sql = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE Id = %d", $id );
        return $this->wpdb->get_row( $sql, ARRAY_A );
    }

    public function get_all( array $args = [] ) {
        $defaults = [ 'orderby' => 'Name', 'order' => 'ASC', 'active_only' => false ];
        $args     = wp_parse_args( $args, $defaults );

        $where = $args['active_only'] ? 'WHERE IsActive = 1' : '';
        $order = esc_sql( $args['orderby'] ) . ' ' . ( strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC' );

        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table} {$where} ORDER BY {$order}",
            ARRAY_A
        ) ?? [];
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

    public function update( array $data, array $where ) {
        $result = $this->wpdb->update(
            $this->table,
            $data,
            $where,
            $this->get_format( $data ),
            [ '%d' ]
        );

        if ( $result === false ) {
            error_log( 'MYVH Organisation Repository Error (update): ' . $this->wpdb->last_error );
            return false;
        }

        return true;
    }

    public function delete( int $id ) {
        $result = $this->wpdb->delete( $this->table, [ 'Id' => $id ], [ '%d' ] );

        if ( $result === false ) {
            error_log( 'MYVH Organisation Repository Error (delete): ' . $this->wpdb->last_error );
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
