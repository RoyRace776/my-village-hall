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
                    COALESCE(c.Name, i.BillingName, i.BillingOrganisationName, '') as CustomerName,
                    COALESCE(c.Email, i.BillingEmail, '') as CustomerEmail,
                    COALESCE(MAX(b.OrganisationId), 0) as OrganisationId,
                    CASE
                        WHEN COALESCE(MAX(b.OrganisationId), 0) = 0
                         AND COALESCE(MAX(i.BillingOrganisationName), '') = ''
                        THEN 1
                        ELSE 0
                    END as IsPersonalInvoice,
                    COALESCE(MAX(o.Name), MAX(i.BillingOrganisationName), '') as OrganisationName
                FROM $this->table_name i
                LEFT JOIN {$this->wpdb->prefix}myvh_customers c ON i.CustomerId = c.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_invoice_items ii ON i.Id = ii.InvoiceId
                LEFT JOIN {$this->wpdb->prefix}myvh_bookings b ON ii.BookingId = b.Id
                LEFT JOIN {$this->wpdb->prefix}myvh_organisations o ON b.OrganisationId = o.Id
                GROUP BY i.Id
                ORDER BY i.InvoiceDate DESC";

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if ($results === null) {
            error_log('MYVH Invoice Repository Error (get_all_with_customers): ' . $this->wpdb->last_error);
        }

        return $results;
    }

    /**
     * Get one invoice with customer and organisation metadata.
     *
     * @param int $invoice_id The invoice ID.
     * @return array|null
     */
    public function get_detail($invoice_id): ?array {
        $sql = $this->wpdb->prepare(
            "SELECT
                i.*,
                COALESCE(c.Name, i.BillingName, i.BillingOrganisationName, '') as CustomerName,
                COALESCE(c.Email, i.BillingEmail, '') as CustomerEmail,
                COALESCE(MAX(b.OrganisationId), 0) as OrganisationId,
                COALESCE(MAX(o.Name), MAX(i.BillingOrganisationName), '') as OrganisationName,
                COUNT(DISTINCT ii.BookingId) as BookingCount
            FROM $this->table_name i
            LEFT JOIN {$this->wpdb->prefix}myvh_customers c ON i.CustomerId = c.Id
            LEFT JOIN {$this->wpdb->prefix}myvh_invoice_items ii ON i.Id = ii.InvoiceId
            LEFT JOIN {$this->wpdb->prefix}myvh_bookings b ON ii.BookingId = b.Id
            LEFT JOIN {$this->wpdb->prefix}myvh_organisations o ON b.OrganisationId = o.Id
            WHERE i.Id = %d
            GROUP BY i.Id",
            intval($invoice_id)
        );

        $result = $this->wpdb->get_row($sql, ARRAY_A);

        if ($result === null && !empty($this->wpdb->last_error)) {
            error_log('MYVH Invoice Repository Error (get_detail): ' . $this->wpdb->last_error);
        }

        return $result;
    }

    /**
     * Get one invoice detail record if the customer can access it in the portal.
     *
     * @param int $invoice_id The invoice ID.
     * @param int $customer_id The customer ID.
     * @return array|null
     */
    public function get_portal_detail($invoice_id, $customer_id): ?array {
        $invoice_id = intval($invoice_id);
        $customer_id = intval($customer_id);

        $sql = "
            SELECT
                i.*,
                COALESCE(c.Name, i.BillingName, i.BillingOrganisationName, '') as CustomerName,
                COALESCE(c.Email, i.BillingEmail, '') as CustomerEmail,
                COALESCE(MAX(b.OrganisationId), 0) as OrganisationId,
                CASE WHEN i.CustomerId = %d THEN 1 ELSE 0 END as IsPersonalInvoice,
                COALESCE(MAX(o.Name), MAX(i.BillingOrganisationName), '') as OrganisationName,
                COUNT(DISTINCT ii.BookingId) as BookingCount
            FROM $this->table_name i
            LEFT JOIN {$this->wpdb->prefix}myvh_customers c ON i.CustomerId = c.Id
            LEFT JOIN {$this->wpdb->prefix}myvh_invoice_items ii ON i.Id = ii.InvoiceId
            LEFT JOIN {$this->wpdb->prefix}myvh_bookings b ON ii.BookingId = b.Id
            LEFT JOIN {$this->wpdb->prefix}myvh_organisations o ON b.OrganisationId = o.Id
            WHERE i.Id = %d
              AND (
                    i.CustomerId = %d
                    OR b.OrganisationId IN (
                        SELECT om.OrganisationId
                        FROM {$this->wpdb->prefix}myvh_organisation_members om
                        WHERE om.CustomerId = %d
                          AND om.IsOrganisationAdmin = 1
                    )
              )
            GROUP BY i.Id
        ";

        $prepared_sql = $this->wpdb->prepare($sql, $customer_id, $invoice_id, $customer_id, $customer_id);
        $result = $this->wpdb->get_row($prepared_sql, ARRAY_A);

        if ($result === null && !empty($this->wpdb->last_error)) {
            error_log('MYVH Invoice Repository Error (get_portal_detail): ' . $this->wpdb->last_error);
        }

        return $result;
    }

    /**
     * Get invoice items with linked booking data.
     *
     * @param int $invoice_id The invoice ID.
     * @return array
     */
    public function get_items_for_invoice($invoice_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT
                ii.*,
                b.StartDate,
                b.EndDate,
                b.StartTime,
                b.EndTime,
                b.Description as BookingDescription,
                b.Status as BookingStatus,
                b.CustomerId as BookingCustomerId,
                r.Name as RoomName,
                o.Name as OrganisationName,
                c.Name as BookingCustomerName
            FROM {$this->wpdb->prefix}myvh_invoice_items ii
            LEFT JOIN {$this->wpdb->prefix}myvh_bookings b ON ii.BookingId = b.Id
            LEFT JOIN {$this->wpdb->prefix}myvh_rooms r ON b.RoomId = r.Id
            LEFT JOIN {$this->wpdb->prefix}myvh_organisations o ON b.OrganisationId = o.Id
            LEFT JOIN {$this->wpdb->prefix}myvh_customers c ON b.CustomerId = c.Id
            WHERE ii.InvoiceId = %d
            ORDER BY ii.DisplayOrder ASC, ii.Id ASC",
            intval($invoice_id)
        );

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if ($results === null) {
            error_log('MYVH Invoice Repository Error (get_items_for_invoice): ' . $this->wpdb->last_error);
            return [];
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
    public function get_next_invoice_number($prefix): int {
        // $sql = "SELECT MAX(CAST(REGEXP_REPLACE(InvoiceNumber, '[^0-9]', '') AS UNSIGNED)) as MaxNumber
        //         FROM $this->table_name
        //         WHERE InvoiceNumber LIKE %s";

        $sql = "SELECT MAX(CAST(RIGHT(InvoiceNumber, 6) AS UNSIGNED)) as MaxNumber
        FROM $this->table_name
        WHERE InvoiceNumber LIKE %s";

        $result = $this->wpdb->get_var($this->wpdb->prepare($sql, $prefix . '%'));
        //Put in the check for null as we'll get null if the prefix is changed to a brand new prefix
        if ($result === null) {
            $result = 0;
        }

        if ( $this->wpdb->last_error ) {
            error_log( 'MYVH Invoice Repository Error (get_next_invoice_number): ' . $this->wpdb->last_error );
            return 1;
        }

        return intval($result) + 1;
    }

    /**
     * Get the stored PDF path for an invoice, or null if none has been recorded.
     *
     * @param int $invoiceId
     * @return string|null
     */
    public function get_pdf_path(int $invoiceId): ?string {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT PdfPath FROM {$this->table_name} WHERE Id = %d",
                $invoiceId
            ),
            ARRAY_A
        );

        return isset($row['PdfPath']) ? (string) $row['PdfPath'] : null;
    }

    /**
     * Persist the PDF path for an invoice.
     *
     * @param int    $invoiceId
     * @param string $path  Absolute filesystem path to the generated PDF.
     * @return bool True on success, false on failure.
     */
    public function set_pdf_path(int $invoiceId, string $path): bool {
        return $this->update(['PdfPath' => $path], ['Id' => $invoiceId]);
    }

}
