<?php
namespace MYVH\Invoices;

use MYVH\Core\Support\RepositoryBase;

/**
 * Repository class for myvh_invoice_items table
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class InvoiceItemRepository extends RepositoryBase{

    /**
     * Constructor
     */
    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'myvh_invoice_items';
    }

    /**
     * Get non-cancelled invoice IDs linked to a booking.
     *
     * @param int $booking_id
     * @return array<int,int>
     */
    public function get_active_invoice_ids_for_booking(int $booking_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT DISTINCT ii.InvoiceId
            FROM {$this->table_name} ii
            INNER JOIN {$this->wpdb->prefix}myvh_invoices i ON i.Id = ii.InvoiceId
            WHERE ii.BookingId = %d
              AND i.Status NOT IN ('cancelled')
            ORDER BY i.InvoiceDate DESC, i.Id DESC",
            $booking_id
        );

        $rows = $this->wpdb->get_col($sql);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map('intval', $rows));
    }

    /**
     * Check whether a booking already has a deposit line item on an invoice.
     *
     * @param int $invoice_id
     * @param int $booking_id
     * @return bool
     */
    public function has_deposit_item(int $invoice_id, int $booking_id): bool {
                $sql = $this->wpdb->prepare(
                        "SELECT COUNT(*)
                        FROM {$this->table_name}
                        WHERE InvoiceId = %d
                            AND BookingId = %d
                            AND ItemType = %s",
                        $invoice_id,
                        $booking_id,
                        'deposit'
                );

        return ((int) $this->wpdb->get_var($sql)) > 0;
    }

    /**
     * Get the next display order index for invoice items.
     *
     * @param int $invoice_id
     * @return int
     */
    public function get_next_display_order(int $invoice_id): int {
        $sql = $this->wpdb->prepare(
            "SELECT COALESCE(MAX(DisplayOrder), -1) FROM {$this->table_name} WHERE InvoiceId = %d",
            $invoice_id
        );

        return ((int) $this->wpdb->get_var($sql)) + 1;
    }

    /**
     * Get deposit invoice items linked to a booking on non-cancelled invoices.
     *
     * @param int $booking_id
     * @return array<int,array<string,mixed>>
     */
    public function get_deposit_items_for_booking(int $booking_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT ii.*, i.InvoiceNumber
            FROM {$this->table_name} ii
            INNER JOIN {$this->wpdb->prefix}myvh_invoices i ON i.Id = ii.InvoiceId
            WHERE ii.BookingId = %d
              AND ii.ItemType = %s
              AND i.Status NOT IN ('cancelled')
            ORDER BY ii.Id ASC",
            $booking_id,
            'deposit'
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Update all invoice item rows of a given type for a specific invoice.
     *
     * @param int $invoice_id
     * @param string $from_type
     * @param string $to_type
     * @return bool
     */
    public function update_item_type_for_invoice(int $invoice_id, string $from_type, string $to_type): bool {
        $result = $this->wpdb->update(
            $this->table_name,
            ['ItemType' => $to_type],
            [
                'InvoiceId' => $invoice_id,
                'ItemType' => $from_type,
            ],
            ['%s'],
            ['%d', '%s']
        );

        return $result !== false;
    }

}
