<?php
/**
 * Repository class for myvh_organisation_member_requests table.
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class OrganisationMemberRequestRepository extends RepositoryBase{

    public function __construct( \wpdb $wpdb ) {
        $this->wpdb  = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_organisation_member_requests';

        $this->ensure_table();
    }

    public function get_pending_by_organisation( int $org_id ): array {
        $sql = $this->wpdb->prepare(
            "SELECT r.*, c.Name AS CustomerName, c.Email AS CustomerEmail
             FROM {$this->table_name} r
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
             FROM {$this->table_name} r
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
             FROM {$this->table_name} r
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
             FROM {$this->table_name}
             WHERE OrganisationId = %d
               AND CustomerId = %d
               AND Status = 'pending'
             LIMIT 1",
            $org_id,
            $customer_id
        );

        return $this->wpdb->get_row( $sql, ARRAY_A );
    }

    private function ensure_table(): void {
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name )
        );

        if ( $exists === $this->table_name ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $collate = $this->wpdb->get_charset_collate();

        dbDelta( "CREATE TABLE {$this->table_name} (
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

}
