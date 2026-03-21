<?php
/**
 * Repository class for myvh_organisation_member_requests table.
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class MYVH_Organisation_Member_Request_Repository {

    /** @var wpdb */
    private $wpdb;

    /** @var string */
    private $table;

    public function __construct( \wpdb $wpdb ) {
        $this->wpdb  = $wpdb;
        $this->table = $wpdb->prefix . 'myvh_organisation_member_requests';

        $this->ensure_table();
    }

    public function create( array $data ) {
        $result = $this->wpdb->insert( $this->table, $data, $this->get_format( $data ) );

        if ( $result === false ) {
            error_log( 'MYVH Organisation_Member_Request Repository Error (create): ' . $this->wpdb->last_error );
            return false;
        }

        return $this->wpdb->insert_id;
    }

    public function get_by_id( int $id ) {
        $sql = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE Id = %d", $id );
        return $this->wpdb->get_row( $sql, ARRAY_A );
    }

    public function get_pending_by_organisation( int $org_id ): array {
        $sql = $this->wpdb->prepare(
            "SELECT r.*, c.Name AS CustomerName, c.Email AS CustomerEmail
             FROM {$this->table} r
             JOIN {$this->wpdb->prefix}myvh_customers c ON r.CustomerId = c.Id
             WHERE r.OrganisationId = %d
               AND r.Status = 'pending'
             ORDER BY r.Created ASC",
            $org_id
        );

        return $this->wpdb->get_results( $sql, ARRAY_A ) ?? [];
    }

    public function get_pending_by_customer( int $customer_id ): array {
        $sql = $this->wpdb->prepare(
            "SELECT r.*, o.Name AS OrganisationName
             FROM {$this->table} r
             JOIN {$this->wpdb->prefix}myvh_organisations o ON r.OrganisationId = o.Id
             WHERE r.CustomerId = %d
               AND r.Status = 'pending'
             ORDER BY r.Created DESC",
            $customer_id
        );

        return $this->wpdb->get_results( $sql, ARRAY_A ) ?? [];
    }

    public function get_pending_for_admin_customer( int $customer_id ): array {
        $sql = $this->wpdb->prepare(
            "SELECT r.*, c.Name AS CustomerName, c.Email AS CustomerEmail, o.Name AS OrganisationName
             FROM {$this->table} r
             JOIN {$this->wpdb->prefix}myvh_organisation_members m
               ON m.OrganisationId = r.OrganisationId
             JOIN {$this->wpdb->prefix}myvh_customers c
               ON c.Id = r.CustomerId
             JOIN {$this->wpdb->prefix}myvh_organisations o
               ON o.Id = r.OrganisationId
             WHERE m.CustomerId = %d
               AND m.IsOrganisationAdmin = 1
               AND r.Status = 'pending'
             ORDER BY r.Created ASC",
            $customer_id
        );

        return $this->wpdb->get_results( $sql, ARRAY_A ) ?? [];
    }

    public function get_pending_request( int $org_id, int $customer_id ) {
        $sql = $this->wpdb->prepare(
            "SELECT *
             FROM {$this->table}
             WHERE OrganisationId = %d
               AND CustomerId = %d
               AND Status = 'pending'
             LIMIT 1",
            $org_id,
            $customer_id
        );

        return $this->wpdb->get_row( $sql, ARRAY_A );
    }

    public function update( int $id, array $data ) {
        $result = $this->wpdb->update(
            $this->table,
            $data,
            [ 'Id' => $id ],
            $this->get_format( $data ),
            [ '%d' ]
        );

        if ( $result === false ) {
            error_log( 'MYVH Organisation_Member_Request Repository Error (update): ' . $this->wpdb->last_error );
            return false;
        }

        return true;
    }

    private function ensure_table(): void {
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table )
        );

        if ( $exists === $this->table ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $collate = $this->wpdb->get_charset_collate();

        dbDelta( "CREATE TABLE {$this->table} (
            Id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            OrganisationId       INT UNSIGNED NOT NULL,
            CustomerId           INT UNSIGNED NOT NULL,
            Status               VARCHAR(20) NOT NULL DEFAULT 'pending',
            RequestMessage       VARCHAR(500) NULL,
            ReviewedByCustomerId INT UNSIGNED NULL,
            ReviewedAt           DATETIME NULL,
            Created              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_org_customer_pending (OrganisationId, CustomerId, Status),
            INDEX idx_org_status (OrganisationId, Status),
            INDEX idx_customer_status (CustomerId, Status)
        ) {$collate};" );
    }

    private function get_format( array $data ): array {
        return array_map( function ( $v ) {
            if ( is_int( $v ) ) {
                return '%d';
            }

            if ( is_float( $v ) ) {
                return '%f';
            }

            return '%s';
        }, $data );
    }
}
