<?php
/**
 * Repository class for myvh_customers table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MYVH_Customer_Repository extends MYVH_Repository_Base {


    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_customers';
    }

    /**
     * Get all customers, each with a comma-separated list of their organisation names
     * resolved via myvh_organisation_members.CustomerId -> myvh_customers.CustomerId.
     *
     * @return array
     */
    public function get_all_with_organisations() {
        $sql = "SELECT
                    c.*,
                    GROUP_CONCAT(DISTINCT o.Name ORDER BY o.Name SEPARATOR ', ') AS OrganisationNames,
                    GROUP_CONCAT(DISTINCT o.Id   ORDER BY o.Name SEPARATOR ',')  AS OrganisationIds
                FROM {$this->table_name} c
                LEFT JOIN {$this->wpdb->prefix}myvh_organisation_members om ON c.Id = om.CustomerId
                LEFT JOIN {$this->wpdb->prefix}myvh_organisations o         ON om.OrganisationId = o.Id
                GROUP BY c.Id
                ORDER BY c.Name";

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if ($results === null) {
            error_log('MYVH Customer Repository Error (get_all_with_organisations): ' . $this->wpdb->last_error);
        }

        return $results;
    }

    /**
     * Get all organisations for a single customer (via their WP CustomerId).
     *
     * @param int $customer_id
     * @return array
     */
    public function get_organisations_for_customer( int $customer_id ): array {
        $sql = $this->wpdb->prepare(
            "SELECT o.*
             FROM {$this->table_name} c
             JOIN {$this->wpdb->prefix}myvh_organisation_members om ON c.Id = om.CustomerId
             JOIN {$this->wpdb->prefix}myvh_organisations o         ON om.OrganisationId = o.Id
             WHERE c.Id = %d
             ORDER BY o.Name",
            $customer_id
        );

        return $this->wpdb->get_results($sql, ARRAY_A) ?? [];
    }

    /**
     * Get all organisations for a single WP user (via their WP user id).
     *
     * @param int $user_id
     * @return array
     */
    public function get_organisations_for_user_id( int $user_id ): array {
        $sql = $this->wpdb->prepare(
            "SELECT o.*
             FROM {$this->table_name} c
             JOIN {$this->wpdb->prefix}myvh_organisation_members om ON c.Id = om.CustomerId
             JOIN {$this->wpdb->prefix}myvh_organisations o         ON om.OrganisationId = o.Id
             WHERE c.WPUserId = %d
             ORDER BY o.Name",
            $user_id
        );

        return $this->wpdb->get_results($sql, ARRAY_A) ?? [];
    }

    public function get_customer_id($wp_user) {
        $sql = $this->wpdb->prepare(
            "SELECT Id from {$this->table_name}
            WHERE WPUserId = %d",
            intval($wp_user)
        );

        return $this->wpdb->get_var($sql);
    }

    /**
     * Get customer by email
     *
     * @param string $email The customer email
     * @return array|null The record data or null if not found
     */
    public function get_by_email($email) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE Email = %s",
            $email
        );

        $result = $this->wpdb->get_row($sql, ARRAY_A);

        if ($result === null && $this->wpdb->last_error) {
            error_log('MYVH Customer Repository Error (get_by_email): ' . $this->wpdb->last_error);
        }

        return $result;
    }

    /**
     * Get customer by user ID
     *
     * @param int $user_id The WordPress user ID
     * @return array|null The record data or null if not found
     */
    public function get_by_user_id($user_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE WPUserId = %d",
            $user_id
        );

        $result = $this->wpdb->get_row($sql, ARRAY_A);

        if ($result === null && $this->wpdb->last_error) {
            error_log('MYVH Customer Repository Error (get_by_user_id): ' . $this->wpdb->last_error);
        }

        return $result;
    }

    /**
     * Search customers by name or email
     *
     * @param string $search_term The search term
     * @return array|null Array of records or null on failure
     */
    public function search($search_term) {
        $search_term = '%' . $this->wpdb->esc_like($search_term) . '%';

        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name
             WHERE Name LIKE %s OR Email LIKE %s
             ORDER BY Name",
            $search_term,
            $search_term
        );

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if ($results === null) {
            error_log('MYVH Customer Repository Error (search): ' . $this->wpdb->last_error);
        }

        return $results;
    }

}
