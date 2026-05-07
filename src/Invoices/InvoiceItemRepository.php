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

    private ?bool $has_item_type_column = null;

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
        if ($this->supports_item_type_column()) {
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
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$this->table_name}
                WHERE InvoiceId = %d
                  AND BookingId = %d
                  AND LOWER(TRIM(Description)) = %s",
                $invoice_id,
                $booking_id,
                'deposit'
            );
        }

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
     * Determine whether the invoice items table contains the ItemType column.
     *
     * @return bool
     */
    public function supports_item_type_column(): bool {
        if ($this->has_item_type_column !== null) {
            return $this->has_item_type_column;
        }

        $table = esc_sql($this->table_name);
        $column = $this->wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'ItemType'");
        $this->has_item_type_column = !empty($column);

        return $this->has_item_type_column;
    }

}
