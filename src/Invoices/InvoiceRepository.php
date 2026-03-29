<?php
namespace MYVH\Invoices;

use MYVH\Core\Support\RepositoryBase;
use wpdb;
/**
 * Repository class for myvh_invoices table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class InvoiceRepository extends RepositoryBase{

    /**
     * Constructor
     */
    public function __construct( wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_invoices';
    }


    /**
     * Get all invoices with customer information
     *
     * @return array|null Array of records or null on failure
     */
    public function get_all_with_customers(): ?array {
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
    public function get_by_customer($customer_id): ?array {
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
    public function get_by_booking($booking_id): ?array {
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
    public function get_by_status($status): ?array {
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
     * Get invoices for customer portal display
     * Retrieves personal invoices and organisation invoices for admin
     *
     * @param int $customer_id The customer ID
     * @param array $statuses Optional array of statuses to filter by
     * @return array|null Array of invoice records with organisation metadata
     */
    public function get_for_customer_portal($customer_id, $statuses = []): ?array {
        $customer_id = intval($customer_id);

        $where = "(i.CustomerId = %d OR b.OrganisationId IN (
            SELECT om.OrganisationId
            FROM {$this->wpdb->prefix}myvh_organisation_members om
            WHERE om.CustomerId = %d
              AND om.IsOrganisationAdmin = 1
        ))";

        $prepare_args = [$customer_id, $customer_id];

        if (!empty($statuses)) {
            $statuses = array_values(array_filter(array_map('sanitize_text_field', (array) $statuses)));
            if (!empty($statuses)) {
                $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
                $where .= " AND i.Status IN ($placeholders)";
                $prepare_args = array_merge($prepare_args, $statuses);
            }
        }

        $sql = "
            SELECT
                i.*,
                COALESCE(MAX(b.OrganisationId), 0) as OrganisationId,
                CASE WHEN i.CustomerId = %d THEN 1 ELSE 0 END as IsPersonalInvoice,
                COALESCE(MAX(o.Name), MAX(i.BillingOrganisationName), '') as OrganisationName
            FROM $this->table_name i
            LEFT JOIN {$this->wpdb->prefix}myvh_invoice_items ii ON i.Id = ii.InvoiceId
            LEFT JOIN {$this->wpdb->prefix}myvh_bookings b ON ii.BookingId = b.Id
            LEFT JOIN {$this->wpdb->prefix}myvh_organisations o ON b.OrganisationId = o.Id
            WHERE $where
            GROUP BY i.Id
            ORDER BY i.InvoiceDate DESC
        ";

        array_unshift($prepare_args, $customer_id);
        $prepared_sql = $this->wpdb->prepare($sql, ...$prepare_args);
        $results = $this->wpdb->get_results($prepared_sql, ARRAY_A);

        if ($results === null) {
            error_log('MYVH Invoice Repository Error (get_for_customer_portal): ' . $this->wpdb->last_error);
        }

        return $results;
    }

    /**
     * Get next invoice number
     *
     * @return int The next invoice number
     */
    public function get_next_invoice_number(): int {
        $sql = "SELECT MAX(CAST(REGEXP_REPLACE(InvoiceNumber, '[^0-9]', '') AS UNSIGNED)) as MaxNumber FROM $this->table_name";

        $result = $this->wpdb->get_var($sql);

        if ( $this->wpdb->last_error ) {
            error_log( 'MYVH Invoice Repository Error (get_next_invoice_number): ' . $this->wpdb->last_error );
            return 1;
        }

        return intval($result) + 1;
    }

}
