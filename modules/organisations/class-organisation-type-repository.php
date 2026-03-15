<?php
/**
 * Repository class for myvh_organisation_types table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class MYVH_Organisation_Type_Repository {

    /** @var wpdb */
    private $wpdb;

    /** @var string */
    private $table;

    public function __construct( \wpdb $wpdb ) {
        $this->wpdb  = $wpdb;
        $this->table = $wpdb->prefix . 'myvh_organisation_types';
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function create( array $data ) {
        $result = $this->wpdb->insert( $this->table, $data, $this->get_format( $data ) );

        if ( $result === false ) {
            error_log( 'MYVH Organisation_Type Repository Error (create): ' . $this->wpdb->last_error );
            return false;
        }

        return $this->wpdb->insert_id;
    }

    public function get_by_id( int $id ) {
        $sql = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE Id = %d", $id );
        return $this->wpdb->get_row( $sql, ARRAY_A );
    }

    public function get_all( array $args = [] ) {
        $defaults = [ 'orderby' => 'Name', 'order' => 'ASC' ];
        $args     = wp_parse_args( $args, $defaults );

        $order = esc_sql( $args['orderby'] ) . ' ' . ( strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC' );

        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY {$order}",
            ARRAY_A
        ) ?? [];
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
            error_log( 'MYVH Organisation_Type Repository Error (update): ' . $this->wpdb->last_error );
            return false;
        }

        return true;
    }

    public function delete( int $id ) {
        $result = $this->wpdb->delete( $this->table, [ 'Id' => $id ], [ '%d' ] );

        if ( $result === false ) {
            error_log( 'MYVH Organisation_Type Repository Error (delete): ' . $this->wpdb->last_error );
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
