<?php
namespace MYVH\Organisations;

use MYVH\Core\Support\RepositoryBase;
use wpdb;

if (!defined('ABSPATH')) exit;

class OrganisationMemberRepository extends RepositoryBase {
    public function __construct(wpdb $wpdb) {
        $this->table_name = $wpdb->prefix . 'myvh_organisation_members';
        $this->wpdb = $wpdb;
    }
    public function get_by_organisation_and_customer(int $org_id, int $customer_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE OrganisationId = %d AND CustomerId = %d",
            $org_id,
            $customer_id
        );
        return $this->wpdb->get_row($sql, ARRAY_A);
    }
    public function get_by_organisation(int $org_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT om.*, u.Name AS Name, u.Email AS Email
             FROM {$this->table_name} om
             LEFT JOIN {$this->wpdb->prefix}myvh_customers u ON om.CustomerId = u.Id
             WHERE om.OrganisationId = %d
             ORDER BY u.Name",
            $org_id
        );
        return $this->wpdb->get_results($sql, ARRAY_A) ?? [];
    }
    public function get_by_user(int $user_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT om.*, o.Name AS OrganisationName
             FROM {$this->table_name} om
             LEFT JOIN {$this->wpdb->prefix}myvh_organisations o ON om.OrganisationId = o.Id
             WHERE om.CustomerId = %d",
            $user_id
        );
        return $this->wpdb->get_results($sql, ARRAY_A) ?? [];
    }
    public function get_admin_organisations_for_customer(int $customer_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT o.*
             FROM {$this->table_name} om
             JOIN {$this->wpdb->prefix}myvh_organisations o ON om.OrganisationId = o.Id
             WHERE om.CustomerId = %d
               AND om.IsOrganisationAdmin = 1
               AND o.IsSystem = 0
             ORDER BY o.Name",
            $customer_id
        );
        return $this->wpdb->get_results($sql, ARRAY_A) ?? [];
    }
    public function exists(int $org_id, int $user_id): bool {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE OrganisationId = %d AND CustomerId = %d",
            $org_id, $user_id
        );
        return (int) $this->wpdb->get_var($sql) > 0;
    }
    public function is_customer_admin(int $org_id, int $customer_id): bool {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$this->table_name}
             WHERE OrganisationId = %d
               AND CustomerId = %d
               AND IsOrganisationAdmin = 1",
            $org_id,
            $customer_id
        );
        return (int) $this->wpdb->get_var($sql) > 0;
    }
    public function count_admins_for_organisation(int $org_id): int {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$this->table_name}
             WHERE OrganisationId = %d
               AND IsOrganisationAdmin = 1",
            $org_id
        );
        return (int) $this->wpdb->get_var($sql);
    }
    public function update_admin_status(int $member_id, bool $is_admin) {
        $result = $this->wpdb->update(
            $this->table_name,
            [ 'IsOrganisationAdmin' => $is_admin ? 1 : 0 ],
            [ 'Id' => $member_id ],
            [ '%d' ],
            [ '%d' ]
        );
        if ($result === false) {
            error_log('MYVH Organisation_Member Repository Error (update_admin_status): ' . $this->wpdb->last_error);
            return false;
        }
        return true;
    }
    public function delete_by_organisation(int $org_id): bool {
        $result = $this->wpdb->delete($this->table_name, [ 'OrganisationId' => $org_id ], [ '%d' ]);
        if ($result === false) {
            error_log('MYVH Organisation_Member Repository Error (delete_by_organisation): ' . $this->wpdb->last_error);
            return false;
        }
        return true;
    }
}
