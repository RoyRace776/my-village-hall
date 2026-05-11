<?php
namespace MYVH\Invoices;

use MYVH\AutoInvoicing\RecurringBookingAutoInvoiceRuleRepository;
use MYVH\AutoInvoicing\SingleBookingAutoInvoiceRuleRepository;
use MYVH\Bookings\BookingRepository;
use MYVH\Bookings\BookingService;
use MYVH\Bookings\BookingChargeRepository;
use MYVH\Bookings\BookingAddonRepository;
use MYVH\Deposits\DepositService;
use MYVH\Pricing\PricingService;
use MYVH\Addons\AddonRepository;
use MYVH\Customers\CustomerRepository;
use MYVH\Organisations\OrganisationRepository;

use WP_Error;

/**
 * Invoice Generator Service
 *
 * Handles both manual and automatic invoice generation from bookings
 * with support for grouping strategies (per-booking, by-customer, by-organisation)
 */

if (!defined('ABSPATH')) {
    exit;
}

class InvoiceGeneratorService {

    private InvoiceService $invoiceService;
    private InvoiceRepository $invoice_repo;
    private InvoiceItemRepository $invoice_item_repo;
    private BookingRepository $booking_repo;
    private BookingService $booking_service;
    private BookingChargeRepository $booking_charge_repo;
    private BookingAddonRepository $booking_addon_repo;
    private AddonRepository $addon_repo;
    private CustomerRepository $customer_repo;
    private OrganisationRepository $organisation_repo;
    private PricingService $pricing_service;
    private DepositService $deposit_service;
    private SingleBookingAutoInvoiceRuleRepository $single_rule_repository;
    private RecurringBookingAutoInvoiceRuleRepository $recurring_rule_repository;

    public function __construct(
        InvoiceService $invoiceService,
        InvoiceRepository $invoice_repo,
        InvoiceItemRepository $invoice_item_repo,
        BookingRepository $booking_repo,
        BookingService $booking_service,
        BookingChargeRepository $booking_charge_repo,
        BookingAddonRepository $booking_addon_repo,
        AddonRepository $addon_repo,
        CustomerRepository $customer_repo,
        OrganisationRepository $organisation_repo,
        PricingService $pricing_service,
        DepositService $deposit_service,
        SingleBookingAutoInvoiceRuleRepository $single_rule_repository,
        RecurringBookingAutoInvoiceRuleRepository $recurring_rule_repository
    ) {
        $this->invoiceService = $invoiceService;
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
        $this->deposit_service = $deposit_service;
        $this->single_rule_repository = $single_rule_repository;
        $this->recurring_rule_repository = $recurring_rule_repository;
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
     *        - rule_scope: 'single', 'recurring', or 'none'
     * @return array|WP_Error Array of created invoice IDs or WP_Error on failure
     */
    public function generate_invoices_from_bookings($booking_ids, $options = []): array|WP_Error {
        $defaults = [
            'group_by' => 'per_booking',
            'trigger_event' => 'manual',
            'recipient_type' => 'booker',
            'rule_scope' => 'single',
            'rule_id' => 0,
            'due_date_offset_days' => null,
        ];
        $options = wp_parse_args($options, $defaults);
        $rule_scope = $this->normalize_rule_scope($options['rule_scope'] ?? 'single');
        $options['rule_scope'] = $rule_scope;
        $rule_repository = $this->get_rule_repository_for_scope($rule_scope);

        // Resolve group_by and due_date_offset_days from the rules table when a rule_id is provided.
        $rule_id = max(0, intval($options['rule_id'] ?? 0));
        if ($rule_id > 0 && $rule_repository !== null) {
            $rule_record = $rule_repository->get_all_rules();
            foreach ($rule_record as $candidate) {
                if (intval($candidate['Id']) === $rule_id) {
                    $options['group_by'] = (string) ($candidate['GroupBy'] ?? $options['group_by']);
                    if ($options['due_date_offset_days'] === null) {
                        $options['due_date_offset_days'] = max(0, intval($candidate['DueDateOffsetDays'] ?? 30));
                    }
                    break;
                }
            }
        }

        // Fall back to the default rule in the table when no rule_id was supplied.
        if ($options['due_date_offset_days'] === null && $rule_repository !== null) {
            $default_rule_id = $this->get_default_rule_id_for_scope($rule_scope);
            $fallback_due_days = 30;
            if ($default_rule_id > 0) {
                foreach ($rule_repository->get_active_rules() as $candidate) {
                    if (intval($candidate['Id']) === $default_rule_id) {
                        $fallback_due_days = max(0, intval($candidate['DueDateOffsetDays'] ?? 30));
                        break;
                    }
                }
            }
            $options['due_date_offset_days'] = $fallback_due_days;
        }

        if ($options['due_date_offset_days'] === null) {
            $options['due_date_offset_days'] = 30;
        }

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
            if ($this->booking_service->booking_requires_no_invoice($booking_id)) {
                return new WP_Error(
                    'invoice_not_required',
                    sprintf(__('Booking #%d is marked as not requiring an invoice', 'my-village-hall'), $booking_id)
                );
            }

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

    private function normalize_rule_scope($rule_scope): string {
        $scope = strtolower((string) $rule_scope);

        if (in_array($scope, ['single', 'recurring', 'none'], true)) {
            return $scope;
        }

        return 'single';
    }

    private function get_rule_repository_for_scope(string $rule_scope): ?object {
        if ($rule_scope === 'single') {
            return $this->single_rule_repository;
        }

        if ($rule_scope === 'recurring') {
            return $this->recurring_rule_repository;
        }

        return null;
    }

    private function get_default_rule_id_for_scope(string $rule_scope): int {
        $settings = get_option('myvh_invoicing_settings', []);

        if (!is_array($settings)) {
            $settings = [];
        }

        if ($rule_scope === 'recurring') {
            return intval($settings['recurring_default_rule_id'] ?? 0);
        }

        return intval($settings['single_default_rule_id'] ?? 0);
    }

    /**
     * Group bookings according to strategy
     *
     * @param array $bookings Array of booking details
     * @param string $group_by Grouping strategy
     * @return array Array of grouped bookings (each element is an array of bookings)
     */
    private function group_bookings_for_invoicing($bookings, $group_by): array {
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
    private function create_invoice_from_booking_group($bookings, $options): int|WP_Error {
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

                    $description = trim((string) ($addon['Description'] ?? ''));
                    if ($description === '') {
                        $description = trim((string) ($addon['AddonName'] ?? ''));
                    }

                    if ($description === '' && !empty($addon['AddonId'])) {
                        $addon_record = $this->addon_repo->get_by_id((int) $addon['AddonId']);
                        $description = trim((string) ($addon_record['Description'] ?? ''));
                        if ($description === '') {
                            $description = trim((string) ($addon_record['Name'] ?? ''));
                        }
                    }

                    $invoice_items[] = [
                        'BookingId' => $booking_id,
                        'Description' => $description !== '' ? $description : 'Add-on',
                        'Quantity' => $addon['Quantity'] ?? 1,
                        'UnitPrice' => $addon['UnitPrice'] ?? 0,
                        'TaxRate' => 0,  // Addons typically don't have separate tax row
                        'TaxAmount' => 0,
                        'TotalAmount' => $addon['TotalAmount'] ?? 0,
                        'DisplayOrder' => $display_order++
                    ];
                }
            }

            // Append deposit as a separate line item when room deposit config matches this booking.
            $deposit_item = $this->build_deposit_invoice_item($booking, $booking_id, $display_order);
            if ($deposit_item !== null) {
                $subtotal += (float) ($deposit_item['TotalAmount'] ?? 0);
                $invoice_items[] = $deposit_item;
                $display_order++;
            }
        }

        $total_amount = $subtotal + $tax_amount;

        $due_date_offset = apply_filters(
            'myvh_invoice_due_date_offset',
            max(0, intval($options['due_date_offset_days'] ?? 30))
        );

        $billing_snapshot = $this->build_billing_snapshot($first_booking, $options);

        // Create the invoice
        $invoice_data = [
            'customer_id' => $customer_id,
            'billing_name' => $billing_snapshot['billing_name'] ?? null,
            'billing_organisation_name' => $billing_snapshot['billing_organisation_name'] ?? null,
            'billing_email' => $billing_snapshot['billing_email'] ?? null,
            'billing_address_line1' => $billing_snapshot['billing_address_line1'] ?? null,
            'billing_address_line2' => $billing_snapshot['billing_address_line2'] ?? null,
            'billing_town_city' => $billing_snapshot['billing_town_city'] ?? null,
            'billing_postcode' => $billing_snapshot['billing_postcode'] ?? null,
            'billing_reference' => $billing_snapshot['billing_reference'] ?? null,
            'sub_total' => $subtotal,
            'tax_amount' => $tax_amount,
            'total_amount' => $total_amount,
            'status' => 'draft',
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime("+{$due_date_offset} days")),
            'notes' => sprintf(__('Generated from %d booking(s)', 'my-village-hall'), count($bookings))
        ];

        $invoice_id = $this->invoiceService->save($invoice_data);

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

    /**
     * Build a deposit invoice item for a booking when applicable.
     *
     * @param array $booking
     * @param int $booking_id
     * @param int $display_order
     * @return array<string,mixed>|null
     */
    private function build_deposit_invoice_item(array $booking, int $booking_id, int $display_order): ?array {
        $room_id = intval($booking['RoomId'] ?? 0);
        $end_date = (string) ($booking['EndDate'] ?? '');
        $end_time = (string) ($booking['EndTime'] ?? '');

        if ($room_id <= 0 || $end_date === '' || $end_time === '') {
            return null;
        }

        try {
            $end = new \DateTime(trim($end_date . ' ' . $end_time));
        } catch (\Exception $exception) {
            return null;
        }

        $deposit = $this->deposit_service->evaluate($room_id, $end);
        if ($deposit === null) {
            return null;
        }

        if (($deposit['action'] ?? 'auto_add') !== 'auto_add') {
            return null;
        }

        $amount = round((float) ($deposit['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return null;
        }

        return [
            'BookingId' => $booking_id,
            'ItemType' => 'deposit',
            'Description' => 'Deposit',
            'Quantity' => 1,
            'UnitPrice' => $amount,
            'TaxRate' => 0,
            'TaxAmount' => 0,
            'TotalAmount' => $amount,
            'DisplayOrder' => $display_order,
        ];
    }

    private function build_billing_snapshot($booking, $options): array {
        $group_by = sanitize_key($options['group_by'] ?? 'per_booking');
        $customer_id = intval($booking['CustomerId'] ?? 0);
        $organisation_id = intval($booking['OrganisationId'] ?? 0);

        $customer = $customer_id > 0 ? $this->customer_repo->get_by_id($customer_id) : null;

        if ($group_by === 'by_organisation' && $organisation_id > 0) {
            $organisation = $this->organisation_repo->get_by_id($organisation_id);

            if (!empty($organisation) && !empty($organisation['InvoiceOrganisationBookings'])) {
                return [
                    'billing_name' => $organisation['BillingContactName'] ?? $organisation['Name'] ?? null,
                    'billing_organisation_name' => $organisation['Name'] ?? null,
                    'billing_email' => $organisation['BillingEmail'] ?? $organisation['ContactEmail'] ?? null,
                    'billing_address_line1' => $organisation['BillingAddressLine1'] ?? null,
                    'billing_address_line2' => $organisation['BillingAddressLine2'] ?? null,
                    'billing_town_city' => $organisation['BillingTownCity'] ?? null,
                    'billing_postcode' => $organisation['BillingPostcode'] ?? null,
                    'billing_reference' => $organisation['BillingReference'] ?? null,
                ];
            }
        }

        return [
            'billing_name' => $customer['Name'] ?? null,
            'billing_organisation_name' => null,
            'billing_email' => $customer['Email'] ?? null,
            'billing_address_line1' => $customer['AddressLine1'] ?? null,
            'billing_address_line2' => null,
            'billing_town_city' => null,
            'billing_postcode' => $customer['PostCode'] ?? null,
            'billing_reference' => null,
        ];
    }
}
