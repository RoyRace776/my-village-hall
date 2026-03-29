<?php
namespace MYVH\Payments;
/**
 * Repository class for myvh_payments table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class PaymentRepository extends RepositoryBase{

    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_payments';
    }


    /**
     * Count how many payments exist for a given invoice.
     *
     * @param int $invoice_id
     * @return int
     */
    public function count_by_invoice(int $invoice_id): int {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE InvoiceId = %d",
                $invoice_id
            )
        );
    }
}
