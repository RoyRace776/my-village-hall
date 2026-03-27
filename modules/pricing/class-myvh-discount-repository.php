<?php
/**
 * Repository class for myvh_discounts table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Discount_Repository extends Repository_Base {

    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_discounts';
    }

    /**
     * Create a new record
     *
     * @param array $data Associative array of column => value pairs
     * @return int|false The inserted record ID on success, false on failure
     */
    public function create($data): int|false {
        $result = $this->wpdb->insert(
            $this->table_name,
            $data,
            $this->get_format($data)
        );

        if ($result === false) {
            error_log('MYVH Discount Repository Error (create): ' . $this->wpdb->last_error);
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
    public function get_all($args = array()): array {
        $defaults = array(
            'orderby' => 'Id',
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
            error_log('MYVH Discount Repository Error (get_all): ' . $this->wpdb->last_error);
        }

        return $results;
    }

}
