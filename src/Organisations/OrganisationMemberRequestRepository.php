<?php
namespace MYVH\Organisations;

use MYVH\Core\Support\RepositoryBase;
use wpdb;

if (!defined('ABSPATH')) exit;

class OrganisationMemberRequestRepository extends RepositoryBase {
    public function __construct(wpdb $wpdb) {
        $this->wpdb  = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_organisation_member_requests';
    }
    public function get_pending_by_organisation(int $org_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT r.*, c.Name AS CustomerName, c.Email AS CustomerEmail
             FROM {$this->table_name} r
             JOIN {$this->wpdb->prefix}myvh_customers c ON r.CustomerId = c.Id
             WHERE r.OrganisationId = %d
               AND r.Status = 'pending'
             ORDER BY r.Created ASC",
            $org_id
        );
        return $this->wpdb->get_results($sql, ARRAY_A) ?? [];
    }
    public function get_pending_by_customer(int $customer_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT r.*, o.Name AS OrganisationName
             FROM {$this->table_name} r
             JOIN {$this->wpdb->prefix}myvh_organisations o ON r.OrganisationId = o.Id
             WHERE r.CustomerId = %d
               AND r.Status = 'pending'
             ORDER BY r.Created DESC",
            $customer_id
        );
        return $this->wpdb->get_results($sql, ARRAY_A) ?? [];
    }
    public function get_pending_for_admin_customer(int $customer_id): array {
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
        return $this->wpdb->get_results($sql, ARRAY_A) ?? [];
    }
    public function get_pending_request(int $org_id, int $customer_id) {
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
        return $this->wpdb->get_row($sql, ARRAY_A);
    }
    public function delete_by_organisation(int $org_id): bool {
        $result = $this->wpdb->delete($this->table_name, [ 'OrganisationId' => $org_id ], [ '%d' ]);
        if ($result === false) {
            error_log('MYVH OrganisationMemberRequestRepository Error (delete_by_organisation): ' . $this->wpdb->last_error);
            return false;
        }
        return true;
    }
}
