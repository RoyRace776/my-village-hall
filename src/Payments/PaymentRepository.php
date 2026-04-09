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

use MYVH\Core\Support\RepositoryBase;

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

    /**
     * Get payments for an invoice ordered newest first.
     *
     * @param int $invoice_id
     * @return array
     */
    public function get_by_invoice(int $invoice_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT *
             FROM {$this->table_name}
             WHERE InvoiceId = %d
             ORDER BY PaymentDate DESC, Id DESC",
            $invoice_id
        );

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * Get the total amount paid against an invoice.
     *
     * @param int $invoice_id
     * @return float
     */
    public function get_total_by_invoice(int $invoice_id): float {
        return (float) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COALESCE(SUM(Amount), 0)
                 FROM {$this->table_name}
                 WHERE InvoiceId = %d",
                $invoice_id
            )
        );
    }

    /**
     * Get payments with related invoice and customer information.
     *
     * @param int $invoice_id Optional invoice filter.
     * @return array
     */
    public function get_with_invoice_details(int $invoice_id = 0): array {
        $where = '';
        $args = [];

        if ($invoice_id > 0) {
            $where = 'WHERE p.InvoiceId = %d';
            $args[] = $invoice_id;
        }

        $sql = "SELECT
                    p.*,
                    i.InvoiceNumber,
                    i.Status AS InvoiceStatus,
                    i.TotalAmount,
                    i.AmountPaid,
                    i.AmountDue,
                    COALESCE(c.Name, i.BillingName, i.BillingOrganisationName, '') AS CustomerName,
                    COALESCE(c.Email, i.BillingEmail, '') AS CustomerEmail,
                    i.BillingName,
                    i.BillingOrganisationName
                FROM {$this->table_name} p
                INNER JOIN {$this->wpdb->prefix}myvh_invoices i ON p.InvoiceId = i.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_customers c ON i.CustomerId = c.Id
                {$where}
                ORDER BY p.PaymentDate DESC, p.Id DESC";

        if (!empty($args)) {
            $sql = $this->wpdb->prepare($sql, ...$args);
        }

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($results) ? $results : [];
    }
}
