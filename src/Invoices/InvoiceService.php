<?php

declare(strict_types=1);

namespace MYVH\Invoices;

use MYVH\Payments\PaymentRepository;
use WP_Error;

if (!defined('ABSPATH')) exit;

class InvoiceService {

    public const VALID_STATUSES = ['draft', 'sent', 'part-paid', 'paid', 'overdue', 'cancelled'];

    private InvoiceRepository $repo;
    private InvoiceItemRepository $invoice_item_repo;
    private PaymentRepository $payment_repo;
    private InvoicePdfRenderer $pdf_renderer;
    private PdfGenerator $pdf_generator;
    private InvoiceFileStorage $file_storage;

    public function __construct(
        InvoiceRepository $repo,
        InvoiceItemRepository $invoice_item_repo,
        PaymentRepository $payment_repo,
        InvoicePdfRenderer $pdf_renderer,
        PdfGenerator $pdf_generator,
        InvoiceFileStorage $file_storage
    ) {
        $this->repo          = $repo;
        $this->invoice_item_repo = $invoice_item_repo;
        $this->payment_repo  = $payment_repo;
        $this->pdf_renderer  = $pdf_renderer;
        $this->pdf_generator = $pdf_generator;
        $this->file_storage  = $file_storage;
    }

    /**
     * Check whether a booking already has a deposit line item on an invoice.
     *
     * @param int $invoice_id
     * @param int $booking_id
     * @return bool
     */
    public function has_booking_deposit_line_item(int $invoice_id, int $booking_id): bool {
        return $this->invoice_item_repo->has_deposit_item($invoice_id, $booking_id);
    }

    /**
     * Add a booking-linked invoice line item and refresh invoice totals.
     *
     * @param int $invoice_id
     * @param int $booking_id
     * @param string $type
     * @param float $amount
     * @param string $description
     * @return bool|WP_Error
     */
    public function add_booking_line_item(
        int $invoice_id,
        int $booking_id,
        string $type,
        float $amount,
        string $description
    ): bool|WP_Error {
        $invoice = $this->repo->get_by_id($invoice_id);
        if (!$invoice) {
            return new WP_Error('not_found', __('Invoice not found', 'my-village-hall'));
        }

        if (($invoice['Status'] ?? '') === 'cancelled') {
            return new WP_Error('validation', __('Cannot add line items to cancelled invoices', 'my-village-hall'));
        }

        if ($amount <= 0) {
            return new WP_Error('validation', __('Line item amount must be greater than zero', 'my-village-hall'));
        }

        $amount = round($amount, 2);
        $normalized_type = sanitize_key($type);
        if ($normalized_type === 'deposit' && $this->invoice_item_repo->has_deposit_item($invoice_id, $booking_id)) {
            return true;
        }

        $item_data = [
            'InvoiceId' => $invoice_id,
            'BookingId' => $booking_id,
            'Description' => sanitize_text_field($description),
            'Quantity' => 1,
            'UnitPrice' => $amount,
            'TaxRate' => 0,
            'TaxAmount' => 0,
            'TotalAmount' => $amount,
            'DisplayOrder' => $this->invoice_item_repo->get_next_display_order($invoice_id),
        ];

        if ($this->invoice_item_repo->supports_item_type_column()) {
            $item_data['ItemType'] = $normalized_type !== '' ? $normalized_type : 'charge';
        }

        $item_created = $this->invoice_item_repo->create($item_data);

        if ($item_created === false) {
            return new WP_Error('database', __('Failed to create invoice line item', 'my-village-hall'));
        }

        $sub_total = round((float) ($invoice['SubTotal'] ?? 0) + $amount, 2);
        $total_amount = round((float) ($invoice['TotalAmount'] ?? 0) + $amount, 2);
        $amount_paid = round((float) ($invoice['AmountPaid'] ?? 0), 2);
        $amount_due = max(0.0, round($total_amount - $amount_paid, 2));

        $updated = $this->repo->update([
            'SubTotal' => $sub_total,
            'TotalAmount' => $total_amount,
            'AmountDue' => $amount_due,
        ], ['Id' => $invoice_id]);

        if ($updated === false) {
            return new WP_Error('database', __('Failed to update invoice totals after adding line item', 'my-village-hall'));
        }

        return $this->refresh_payment_state($invoice_id);
    }

    public function get_all($args = []): array {
        return $this->repo->get_all($args);
    }

    public function get($id): ?array {
        return $this->repo->get_by_id($id);
    }

    public function get_with_customers(): ?array {
        return $this->repo->get_all_with_customers();
    }

    public function get_detail($id): ?array {
        $invoice = $this->repo->get_detail($id);

        if (!$invoice) {
            return null;
        }

        $invoice['Items'] = $this->repo->get_items_for_invoice($id);
        $invoice['BookingsSummary'] = $this->build_booking_summary($invoice['Items']);
        $invoice['Payments'] = $this->payment_repo->get_by_invoice((int) $id);

        return $invoice;
    }

    public function get_detail_for_portal($id, $customer_id, bool $is_client_admin = false): ?array {
        $invoice = $is_client_admin
            ? $this->repo->get_detail($id)
            : $this->repo->get_portal_detail($id, $customer_id);

        if (!$invoice) {
            return null;
        }

        $invoice['Items'] = $this->repo->get_items_for_invoice($id);
        $invoice['BookingsSummary'] = $this->build_booking_summary($invoice['Items']);
        $invoice['Payments'] = $this->payment_repo->get_by_invoice((int) $id);

        return $invoice;
    }

    public function get_valid_statuses(): array {
        return self::VALID_STATUSES;
    }

    public function get_status_label(string $status): string {
        return ucwords(str_replace('-', ' ', $status));
    }

    private function build_booking_summary(array $items): array {
        $bookings = [];

        foreach ($items as $item) {
            $booking_id = intval($item['BookingId'] ?? 0);
            if ($booking_id <= 0) {
                continue;
            }

            if (!isset($bookings[$booking_id])) {
                $bookings[$booking_id] = [
                    'BookingId' => $booking_id,
                    'Description' => $item['BookingDescription'] ?: ($item['Description'] ?? ''),
                    'StartDate' => $item['StartDate'] ?? null,
                    'EndDate' => $item['EndDate'] ?? null,
                    'StartTime' => $item['StartTime'] ?? null,
                    'EndTime' => $item['EndTime'] ?? null,
                    'RoomName' => $item['RoomName'] ?? null,
                    'OrganisationName' => $item['OrganisationName'] ?? null,
                    'BookingCustomerName' => $item['BookingCustomerName'] ?? null,
                    'BookingStatus' => $item['BookingStatus'] ?? null,
                    'TotalAmount' => 0.0,
                ];
            }

            $bookings[$booking_id]['TotalAmount'] += floatval($item['TotalAmount'] ?? 0);
        }

        return array_values($bookings);
    }

    public function get_by_customer($customer_id): ?array {
        return $this->repo->get_by_customer($customer_id);
    }

    public function get_by_booking($booking_id): ?array {
        return $this->repo->get_by_booking($booking_id);
    }

    public function get_by_status($status): ?array {
        return $this->repo->get_by_status($status);
    }

    public function save($data): int|WP_Error {

        if (empty($data['customer_id'])) {
            return new WP_Error('validation', __('Customer is required', 'my-village-hall'));
        }

        if (!isset($data['total_amount']) || floatval($data['total_amount']) < 0) {
            return new WP_Error('validation', __('Valid total amount is required', 'my-village-hall'));
        }

        if (empty($data['invoice_id'])) {
            $invoice_number_formatted = $this->get_next_invoice_number();
        } else {
            $invoice_number_formatted = $data['invoice_number'];
        }

        $total_amount = floatval($data['total_amount']);
        $amount_paid = floatval($data['amount_paid'] ?? 0);
        $requested_status = sanitize_key($data['status'] ?? 'draft');

        $record = [
            'InvoiceNumber' => $invoice_number_formatted,
            'CustomerId' => intval($data['customer_id']),
            'BillingName' => !empty($data['billing_name']) ? sanitize_text_field($data['billing_name']) : null,
            'BillingOrganisationName' => !empty($data['billing_organisation_name']) ? sanitize_text_field($data['billing_organisation_name']) : null,
            'BillingEmail' => !empty($data['billing_email']) ? sanitize_email($data['billing_email']) : null,
            'BillingAddressLine1' => !empty($data['billing_address_line1']) ? sanitize_text_field($data['billing_address_line1']) : null,
            'BillingAddressLine2' => !empty($data['billing_address_line2']) ? sanitize_text_field($data['billing_address_line2']) : null,
            'BillingTownCity' => !empty($data['billing_town_city']) ? sanitize_text_field($data['billing_town_city']) : null,
            'BillingPostcode' => !empty($data['billing_postcode']) ? sanitize_text_field($data['billing_postcode']) : null,
            'BillingReference' => !empty($data['billing_reference']) ? sanitize_text_field($data['billing_reference']) : null,
            'InvoiceDate' => !empty($data['invoice_date']) ? sanitize_text_field($data['invoice_date']) : date('Y-m-d'),
            'DueDate' => !empty($data['due_date']) ? sanitize_text_field($data['due_date']) : date('Y-m-d', strtotime('+14 days')),
            'SubTotal' => floatval($data['sub_total'] ?? 0),
            'TaxAmount' => floatval($data['tax_amount'] ?? 0),
            'TotalAmount' => $total_amount,
            'AmountPaid' => $amount_paid,
            'AmountDue' => max(0.0, $total_amount - $amount_paid),
            'Status' => $this->derive_status([
                'Status' => $requested_status,
                'DueDate' => $data['due_date'] ?? null,
                'TotalAmount' => $total_amount,
            ], $amount_paid),
            'Notes' => sanitize_textarea_field($data['notes'] ?? ''),
        ];

        if (!empty($data['invoice_id'])) {
            $invoice_id = intval($data['invoice_id']);

            if ($record['Status'] === 'cancelled' && $this->payment_repo->count_by_invoice($invoice_id) > 0) {
                return new WP_Error('validation', __('Invoices with payments cannot be cancelled.', 'my-village-hall'));
            }

            $result = $this->repo->update($record, ['Id' => $invoice_id]);
            if ($result === false) {
                return new WP_Error('database', __('Failed to update invoice', 'my-village-hall'));
            }
            return $invoice_id;
        }

        $result = $this->repo->create($record);
        if ($result === false) {
            return new WP_Error('database', __('Failed to create invoice', 'my-village-hall'));
        }

        return $result;
    }

    protected function get_next_invoice_number(): string {
        $prefix = myvh_setting('invoicing.prefix', 'INV-');
        $invoice_number = $this->repo->get_next_invoice_number($prefix);

        if ($invoice_number > 999000) {
            error_log('MYVH Invoice Service Warning: Maximum invoice number nearly reached for prefix ' . $prefix);

            //TODO: We should ideally have a better strategy for handling this scenario, such as allowing admin to set a new prefix and/or archiving old invoices. For now, we'll just append a '1' to the prefix to allow continued invoicing, but this is not a long-term solution.
            if ($invoice_number === 999999) {
                error_log('MYVH Invoice Service Error: Maximum invoice number reached for prefix ' . $prefix);
                throw new \RuntimeException('Maximum invoice number reached for prefix ' . $prefix);
            }
        }

        $invoice_number_formatted = $prefix . str_pad((string) $invoice_number, 6, '0', STR_PAD_LEFT);
        return $invoice_number_formatted;
    }

    public function delete($id): int|WP_Error {
        if ($this->payment_repo->count_by_invoice($id) > 0) {
            return new WP_Error('validation', __('Cannot delete invoice with existing payments', 'my-village-hall'));
        }

        if (!$this->file_storage->delete((int) $id)) {
            return new WP_Error('filesystem', __('Failed to delete invoice PDF file', 'my-village-hall'));
        }

        $deleted = $this->repo->delete($id);

        if ($deleted === false) {
            return new WP_Error('database', __('Failed to delete invoice', 'my-village-hall'));
        }

        return (int) $id;
    }

    public function get_for_portal($customer_id, $statuses = []): ?array {
        return $this->repo->get_for_customer_portal($customer_id, $statuses);
    }

    public function update_status($id, $status): bool|WP_Error {
        $status = sanitize_key($status);
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return new WP_Error('validation', __('Invalid invoice status', 'my-village-hall'));
        }

        $invoice = $this->repo->get_by_id($id);
        if (!$invoice) {
            return new WP_Error('not_found', __('Invoice not found', 'my-village-hall'));
        }

        $payment_count = $this->payment_repo->count_by_invoice((int) $id);
        if ($status === 'cancelled' && $payment_count > 0) {
            return new WP_Error('validation', __('Invoices with payments cannot be cancelled.', 'my-village-hall'));
        }

        if ($payment_count > 0) {
            $expected_status = $this->derive_status($invoice, $this->payment_repo->get_total_by_invoice((int) $id));
            if ($status !== $expected_status) {
                return new WP_Error('validation', __('Invoice status is managed by recorded payments.', 'my-village-hall'));
            }
        }

        return $this->repo->update(['Status' => $status], ['Id' => $id]);
    }

    public function record_payment(
        int $id,
        float $amount,
        string $method = 'other',
        string $payment_date = '',
        string $reference = '',
        string $notes = ''
    ): int|WP_Error {
        $invoice = $this->repo->get_by_id($id);

        if (!$invoice) {
            return new WP_Error('not_found', __('Invoice not found', 'my-village-hall'));
        }

        if (($invoice['Status'] ?? '') === 'cancelled') {
            return new WP_Error('validation', __('Cannot add payments to a cancelled invoice.', 'my-village-hall'));
        }

        if ($amount <= 0) {
            return new WP_Error('validation', __('Payment amount is required.', 'my-village-hall'));
        }

        $result = $this->payment_repo->create([
            'InvoiceId' => $id,
            'PaymentDate' => $payment_date !== '' ? $payment_date : current_time('Y-m-d'),
            'Amount' => round($amount, 2),
            'PaymentMethod' => $method,
            'TransactionReference' => $reference !== '' ? $reference : null,
            'Notes' => $notes !== '' ? $notes : null,
        ]);

        if ($result === false) {
            return new WP_Error('database', __('Failed to create payment.', 'my-village-hall'));
        }

        $refresh = $this->refresh_payment_state($id);
        if (is_wp_error($refresh)) {
            return $refresh;
        }

        return $result;
    }

    public function delete_payment(int $payment_id): bool|WP_Error {
        $payment = $this->payment_repo->get_by_id($payment_id);
        if (!$payment) {
            return new WP_Error('not_found', __('Payment not found.', 'my-village-hall'));
        }

        $deleted = $this->payment_repo->delete($payment_id);
        if ($deleted === false) {
            return new WP_Error('database', __('Failed to delete payment.', 'my-village-hall'));
        }

        return $this->refresh_payment_state((int) ($payment['InvoiceId'] ?? 0));
    }

    public function refresh_payment_state(int $invoice_id): bool|WP_Error {
        $invoice = $this->repo->get_by_id($invoice_id);
        if (!$invoice) {
            return new WP_Error('not_found', __('Invoice not found', 'my-village-hall'));
        }

        $amount_paid = round($this->payment_repo->get_total_by_invoice($invoice_id), 2);
        $total_amount = round((float) ($invoice['TotalAmount'] ?? 0), 2);
        $amount_due = max(0.0, round($total_amount - $amount_paid, 2));
        $status = $this->derive_status($invoice, $amount_paid);

        return $this->repo->update([
            'AmountPaid' => $amount_paid,
            'AmountDue' => $amount_due,
            'Status' => $status,
        ], ['Id' => $invoice_id]);
    }

    private function derive_status(array $invoice, float $amount_paid): string {
        $current_status = sanitize_key($invoice['Status'] ?? 'draft');
        $total_amount = round((float) ($invoice['TotalAmount'] ?? 0), 2);
        $amount_due = max(0.0, round($total_amount - $amount_paid, 2));

        if ($current_status === 'cancelled' && $amount_paid <= 0.00001) {
            return 'cancelled';
        }

        if ($total_amount > 0 && $amount_due <= 0.00001) {
            return 'paid';
        }

        if ($amount_paid > 0.00001) {
            return 'part-paid';
        }

        if ($current_status === 'draft') {
            return 'draft';
        }

        $due_timestamp = !empty($invoice['DueDate']) ? strtotime((string) $invoice['DueDate'] . ' 23:59:59') : false;
        $now_timestamp = current_time('timestamp');

        if ($due_timestamp !== false && $due_timestamp < $now_timestamp) {
            return 'overdue';
        }

        return 'sent';
    }

    // ── PDF generation ────────────────────────────────────────────────────────

    /**
     * Generate a PDF for the given invoice and return its absolute path.
     * If a PDF has already been generated for this invoice, the existing path
     * is returned without regenerating.
     *
     * @param int $invoiceId
     * @return string|WP_Error Absolute file path on success, WP_Error on failure.
     */
    public function generate_pdf(int $invoiceId): string|WP_Error {
        // 1. Return existing PDF if already stored and file still present on disk.
        if ($this->file_storage->exists($invoiceId)) {
            return (string) $this->file_storage->getPath($invoiceId);
        }

        // 2. Load full invoice detail (includes Items, BookingsSummary, Payments).
        $invoice = $this->get_detail($invoiceId);
        if (!$invoice) {
            return new WP_Error('not_found', __('Invoice not found', 'my-village-hall'));
        }

        // 3. Render HTML.
        $html = $this->pdf_renderer->renderHtml($invoice);

        // 4. Convert HTML to PDF binary.
        $pdf_binary = $this->pdf_generator->render($html);

        // 5. Write the binary to the uploads directory.
        $path = $this->file_storage->save($invoiceId, $pdf_binary);

        // 6. Persist the file path against the invoice record.
        $this->repo->set_pdf_path($invoiceId, $path);

        return $path;
    }

    /**
     * Return a publicly accessible URL for the invoice PDF.
     * The PDF is generated on first call if it does not already exist.
     *
     * @param int $invoiceId
     * @return string|WP_Error Public URL on success, WP_Error on failure.
     */
    public function get_invoice_pdf_url(int $invoiceId): string|WP_Error {
        $path = $this->generate_pdf($invoiceId);

        if (is_wp_error($path)) {
            return $path;
        }

        $url = $this->file_storage->getUrl($invoiceId);

        if ($url === null) {
            return new WP_Error('url_error', __('Could not determine PDF URL.', 'my-village-hall'));
        }

        return $url;
    }
}
