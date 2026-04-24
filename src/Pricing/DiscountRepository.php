<?php
namespace MYVH\Pricing;

use MYVH\Core\Support\RepositoryBase;
/**
 * Repository class for myvh_discounts table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class DiscountRepository extends RepositoryBase {

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
    /** Columns that callers are allowed to sort by. */
    private const ALLOWED_ORDERBY = ['Id', 'Code', 'Amount', 'Type', 'IsActive', 'CreatedAt'];

    public function get_all($args = array()): array {
        $defaults = array(
            'orderby' => 'Id',
            'order' => 'ASC',
            'limit' => null,
            'offset' => null
        );

        $args = wp_parse_args($args, $defaults);

        $orderby = in_array($args['orderby'], self::ALLOWED_ORDERBY, true) ? $args['orderby'] : 'Id';
        $order   = 'DESC' === strtoupper((string) $args['order']) ? 'DESC' : 'ASC';

        $sql = "SELECT * FROM {$this->table_name}";
        $sql .= " ORDER BY `{$orderby}` {$order}";

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