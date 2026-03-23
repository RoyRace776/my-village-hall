<?php

/**
 * Invoice Generator Service
 *
 * Handles both manual and automatic invoice generation from bookings
 * with support for grouping strategies (per-booking, by-customer, by-organisation)
 */

if (!defined('ABSPATH')) {
    exit;
}

class MYVH_Invoice_Generator_Service {

    private $invoice_service;
    private $invoice_repo;
    private $invoice_item_repo;
    private $booking_repo;
    private $booking_service;
    private $booking_charge_repo;
    private $booking_addon_repo;
    private $addon_repo;
    private $customer_repo;
    private $organisation_repo;
    private $pricing_service;

    public function __construct(
        MYVH_Invoice_Service $invoice_service,
        MYVH_Invoice_Repository $invoice_repo,
        MYVH_Invoice_Item_Repository $invoice_item_repo,
        MYVH_Booking_Repository $booking_repo,
        MYVH_Booking_Service $booking_service,
        MYVH_Booking_Charge_Repository $booking_charge_repo,
        MYVH_Booking_Addon_Repository $booking_addon_repo,
        MYVH_Addon_Repository $addon_repo,
        MYVH_Customer_Repository $customer_repo,
        MYVH_Organisation_Repository $organisation_repo,
        MYVH_Pricing_Service $pricing_service
    ) {
        $this->invoice_service = $invoice_service;
        $this->invoice_repo = $invoice_repo;
        $this->invoice_item_repo = $invoice_item_repo;
        $this->booking_repo = $booking_repo;
        $this->booking_service = $booking_service;
        $this->booking_charge_repo = $booking_charge_repo;
        $this->booking_addon_repo = $booking_addon_repo;
        $this->addon_repo = $addon_repo;
        $this->customer_repo = $customer_repo;
        $this->organisation_repo = $organisation_repo;
        $this->pricing_service = $pricing_service;
    }

    /**
     * Generate invoices from a list of booking IDs
     *
     * Supports different grouping strategies and enforces mutual exclusivity
     *
     * @param array $booking_ids List of booking IDs to invoice
     * @param array $options Configuration options:
     *        - group_by: 'per_booking', 'by_customer', or 'by_organisation'
     *        - trigger_event: 'manual', 'confirmation', 'completion', or custom
     *        - recipient_type: 'booker' or 'organisation_billing' (for future use)
     * @return array|WP_Error Array of created invoice IDs or WP_Error on failure
     */
    public function generate_invoices_from_bookings($booking_ids, $options = []) {
        $defaults = [
            'group_by' => 'per_booking',
            'trigger_event' => 'manual',
            'recipient_type' => 'booker'
        ];
        $options = wp_parse_args($options, $defaults);

        if (empty($booking_ids) || !is_array($booking_ids)) {
            return new WP_Error('invalid_bookings', __('No valid bookings provided', 'my-village-hall'));
        }

        // Sanitize booking IDs
        $booking_ids = array_map('intval', $booking_ids);
        $booking_ids = array_filter($booking_ids);

        if (empty($booking_ids)) {
            return new WP_Error('invalid_bookings', __('No valid bookings provided', 'my-village-hall'));
        }

        // Check mutual exclusivity: ensure none of the bookings are already invoiced
        foreach ($booking_ids as $booking_id) {
            if ($this->booking_service->booking_has_invoices($booking_id)) {
                return new WP_Error(
                    'already_invoiced',
                    sprintf(__('Booking #%d has already been invoiced', 'my-village-hall'), $booking_id)
                );
            }
        }

        // Fetch all booking details
        $bookings = [];
        foreach ($booking_ids as $booking_id) {
            $booking_details = $this->booking_repo->get_all_with_details(['booking_id' => $booking_id]);
            if (empty($booking_details)) {
                return new WP_Error('booking_not_found', sprintf(__('Booking #%d not found', 'my-village-hall'), $booking_id));
            }
            $bookings[] = reset($booking_details);  // Get first result
        }

        // Group bookings according to strategy
        $grouped = $this->group_bookings_for_invoicing($bookings, $options['group_by']);

        // Generate invoices for each group
        $created_invoice_ids = [];

        foreach ($grouped as $group) {
            $invoice_id = $this->create_invoice_from_booking_group($group, $options);

            if (is_wp_error($invoice_id)) {
                return $invoice_id;
            }

            $created_invoice_ids[] = $invoice_id;
        }

        return $created_invoice_ids;
    }

    /**
     * Group bookings according to strategy
     *
     * @param array $bookings Array of booking details
     * @param string $group_by Grouping strategy
     * @return array Array of grouped bookings (each element is an array of bookings)
     */
    private function group_bookings_for_invoicing($bookings, $group_by) {
        $grouped = [];

        switch ($group_by) {
            case 'by_customer':
                // Group by customer ID
                $by_customer = [];
                foreach ($bookings as $booking) {
                    $customer_id = intval($booking['CustomerId']);
                    if (!isset($by_customer[$customer_id])) {
                        $by_customer[$customer_id] = [];
                    }
                    $by_customer[$customer_id][] = $booking;
                }
                $grouped = array_values($by_customer);
                break;

            case 'by_organisation':
                // Group by organisation ID
                $by_org = [];
                foreach ($bookings as $booking) {
                    $org_id = intval($booking['OrganisationId'] ?? 0);
                    if (!isset($by_org[$org_id])) {
                        $by_org[$org_id] = [];
                    }
                    $by_org[$org_id][] = $booking;
                }
                $grouped = array_values($by_org);
                break;

            case 'per_booking':
            default:
                // One invoice per booking
                foreach ($bookings as $booking) {
                    $grouped[] = [$booking];
                }
                break;
        }

        return $grouped;
    }

    /**
     * Create a single invoice from a group of bookings
     *
     * @param array $bookings Array of booking details for this invoice
     * @param array $options Invoice options
     * @return int|WP_Error Invoice ID on success, WP_Error on failure
     */
    private function create_invoice_from_booking_group($bookings, $options) {
        if (empty($bookings)) {
            return new WP_Error('empty_group', __('Cannot create invoice from empty booking group', 'my-village-hall'));
        }

        // Use customer from first booking in group
        $first_booking = reset($bookings);
        $customer_id = intval($first_booking['CustomerId']);

        // Calculate totals from all bookings in group
        $subtotal = 0;
        $tax_amount = 0;
        $invoice_items = [];
        $display_order = 0;

        foreach ($bookings as $booking) {
            $booking_id = intval($booking['Id']);

            // Get room charge for this booking
            $charge = $this->booking_charge_repo->get_by_booking_id($booking_id);
            if ($charge) {
                $charge = reset($charge);  // Get first (should be only one)
                $subtotal += floatval($charge['TotalAmount'] ?? 0);
                $tax_amount += floatval($charge['TaxAmount'] ?? 0);

                $invoice_items[] = [
                    'BookingId' => $booking_id,
                    'Description' => $charge['Description'] ?? 'Room charge',
                    'Quantity' => $charge['Quantity'] ?? 1,
                    'UnitPrice' => $charge['UnitPrice'] ?? 0,
                    'TaxRate' => $charge['TaxRate'] ?? 0,
                    'TaxAmount' => $charge['TaxAmount'] ?? 0,
                    'TotalAmount' => $charge['TotalAmount'] ?? 0,
                    'DisplayOrder' => $display_order++
                ];
            }

            // Get addons for this booking
            $addons = $this->booking_addon_repo->get_by_booking_id($booking_id);
            if (!empty($addons)) {
                foreach ($addons as $addon) {
                    $subtotal += floatval($addon['TotalAmount'] ?? 0);

                    $invoice_items[] = [
                        'BookingId' => $booking_id,
                        'Description' => $addon['Description'] ?? 'Add-on',
                        'Quantity' => $addon['Quantity'] ?? 1,
                        'UnitPrice' => $addon['UnitPrice'] ?? 0,
                        'TaxRate' => 0,  // Addons typically don't have separate tax row
                        'TaxAmount' => 0,
                        'TotalAmount' => $addon['TotalAmount'] ?? 0,
                        'DisplayOrder' => $display_order++
                    ];
                }
            }
        }

        $total_amount = $subtotal + $tax_amount;

        // Get due date from settings (default 30 days)
        $due_date_offset = apply_filters(
            'myvh_invoice_due_date_offset',
            intval(myvh_setting('invoicing.single_due_date_offset_days', 30))
        );

        // Create the invoice
        $invoice_data = [
            'customer_id' => $customer_id,
            'sub_total' => $subtotal,
            'tax_amount' => $tax_amount,
            'total_amount' => $total_amount,
            'status' => 'draft',
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime("+{$due_date_offset} days")),
            'notes' => sprintf(__('Generated from %d booking(s)', 'my-village-hall'), count($bookings))
        ];

        $invoice_id = $this->invoice_service->save($invoice_data);

        if (is_wp_error($invoice_id)) {
            return $invoice_id;
        }

        // Create invoice items
        foreach ($invoice_items as $item) {
            $item['InvoiceId'] = $invoice_id;
            $result = $this->invoice_item_repo->create($item);

            if ($result === false) {
                return new WP_Error(
                    'invoice_item_create_failed',
                    __('Failed to create invoice item', 'my-village-hall')
                );
            }
        }

        // Fire action for invoice created
        do_action('myvh_invoice_generated', $invoice_id, $bookings, $options);

        return $invoice_id;
    }
}
