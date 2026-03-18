<?php
/**
 * Repository class for myvh_customers table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MYVH_Customer_Repository {

    /**
     * @var wpdb
     */
    private $wpdb;

    /**
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_customers';
    }

    /**
     * Create a new record
     *
     * @param array $data Associative array of column => value pairs
     * @return int|false The inserted record ID on success, false on failure
     */
    public function create($data) {
        $result = $this->wpdb->insert(
            $this->table_name,
            $data,
            $this->get_format($data)
        );

        if ($result === false) {
            error_log('MYVH Customer Repository Error (create): ' . $this->wpdb->last_error);
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Get all records
     *
     * @param array $args Optional query arguments (orderby, order, limit, offset)
     * @return array|null Array of records or null on failure
     */
    public function get_all($args = array()) {
        $defaults = array(
            'orderby' => 'Name',
            'order' => 'ASC',
            'limit' => null,
            'offset' => null
        );

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM $this->table_name";
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";

        if ($args['limit'] !== null) {
            $sql .= " LIMIT " . intval($args['limit']);

            if ($args['offset'] !== null) {
                $sql .= " OFFSET " . intval($args['offset']);
            }
        }

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if ($results === null) {
            error_log('MYVH Customer Repository Error (get_all): ' . $this->wpdb->last_error);
        }

        return $results;
    }

    /**
     * Get a record by ID
     *
     * @param int $id The record ID
     * @return array|null The record data or null if not found
     */
    public function get_by_id($id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE Id = %d",
            $id
        );

        $result = $this->wpdb->get_row($sql, ARRAY_A);

        if ($result === null && $this->wpdb->last_error) {
            error_log('MYVH Customer Repository Error (get_by_id): ' . $this->wpdb->last_error);
        }

        return $result;
    }

    /**
     * Update a record
     *
     * @param int $id The record ID
     * @param array $data Associative array of column => value pairs to update
     * @return bool True on success, false on failure
     */
    public function update($data, $id) {
        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            $id,
            $this->get_format($data),
            array('%d')
        );

        if ($result === false) {
            error_log('MYVH Customer Repository Error (update): ' . $this->wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Delete a record
     *
     * @param int $id The record ID
     * @return bool True on success, false on failure
     */
    public function delete($id) {
        $result = $this->wpdb->delete(
            $this->table_name,
            array('Id' => $id),
            array('%d')
        );

        if ($result === false) {
            error_log('MYVH Customer Repository Error (delete): ' . $this->wpdb->last_error);
            return false;
        }

        return true;
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

    /**
     * Get the format array for wpdb operations
     *
     * @param array $data The data array
     * @return array Format array for wpdb
     */
    private function get_format($data) {
        $formats = array();

        foreach ($data as $key => $value) {
            if (is_int($value)) {
                $formats[] = '%d';
            } elseif (is_float($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }
}
