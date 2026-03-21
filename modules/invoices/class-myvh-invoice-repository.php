<?php
/**
 * Repository class for myvh_invoices table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MYVH_Invoice_Repository {
    
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
        $this->table_name = $wpdb->prefix . 'myvh_invoices';
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
            error_log('MYVH Invoice Repository Error (create): ' . $this->wpdb->last_error);
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
            'orderby' => 'InvoiceDate',
            'order' => 'DESC',
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
            error_log('MYVH Invoice Repository Error (get_all): ' . $this->wpdb->last_error);
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
            error_log('MYVH Invoice Repository Error (get_by_id): ' . $this->wpdb->last_error);
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
            error_log('MYVH Invoice Repository Error (update): ' . $this->wpdb->last_error);
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
            error_log('MYVH Invoice Repository Error (delete): ' . $this->wpdb->last_error);
            return false;
        }
        
        return true;
    }

    /**
     * Get all invoices with customer information
     *
     * @return array|null Array of records or null on failure
     */
    public function get_all_with_customers() {
        $sql = "SELECT 
                    i.*,
                    c.Name as CustomerName,
                    c.Email as CustomerEmail
                FROM $this->table_name i
                JOIN {$this->wpdb->prefix}myvh_customers c ON i.CustomerId = c.Id
                ORDER BY i.InvoiceDate DESC";

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        if ($results === null) {
            error_log('MYVH Invoice Repository Error (get_all_with_customers): ' . $this->wpdb->last_error);
        }

        return $results;
    }

    /**
     * Get invoices by customer ID
     *
     * @param int $customer_id The customer ID
     * @return array|null Array of records or null on failure
     */
    public function get_by_customer($customer_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE CustomerId = %d ORDER BY InvoiceDate DESC",
            $customer_id
        );
        
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        if ($results === null) {
            error_log('MYVH Invoice Repository Error (get_by_customer): ' . $this->wpdb->last_error);
        }
        
        return $results;
    }

    /**
     * Get invoices by booking ID
     *
     * @param int $booking_id The booking ID
     * @return array|null Array of records or null on failure
     */
    public function get_by_booking($booking_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE BookingId = %d ORDER BY InvoiceDate DESC",
            $booking_id
        );
        
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        if ($results === null) {
            error_log('MYVH Invoice Repository Error (get_by_booking): ' . $this->wpdb->last_error);
        }
        
        return $results;
    }

    /**
     * Get invoices by status
     *
     * @param string $status The invoice status
     * @return array|null Array of records or null on failure
     */
    public function get_by_status($status) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE Status = %s ORDER BY InvoiceDate DESC",
            $status
        );
        
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        if ($results === null) {
            error_log('MYVH Invoice Repository Error (get_by_status): ' . $this->wpdb->last_error);
        }
        
        return $results;
    }

    /**
     * Get next invoice number
     *
     * @return int The next invoice number
     */
    public function get_next_invoice_number() {
        $sql = "SELECT MAX(CAST(InvoiceNumber AS UNSIGNED)) as MaxNumber FROM $this->table_name";
        
        $result = $this->wpdb->get_var($sql);
        
        if ($result === null) {
            error_log('MYVH Invoice Repository Error (get_next_invoice_number): ' . $this->wpdb->last_error);
            return 1;
        }
        
        return intval($result) + 1;
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
