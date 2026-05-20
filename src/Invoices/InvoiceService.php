<?php

declare(strict_types=1);

namespace MYVH\Invoices;

use MYVH\Payments\PaymentRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WP_Error;

if (!defined('ABSPATH')) exit;

class InvoiceService {

    public const VALID_STATUSES = ['draft', 'sent', 'part-paid', 'paid', 'overdue', 'cancelled'];
    public const VALID_DEPOSIT_OUTCOMES = ['retained', 'refunded'];

    private InvoiceRepository $repo;
    private InvoiceItemRepository $invoice_item_repo;
    private PaymentRepository $payment_repo;
    private InvoicePdfRenderer $pdf_renderer;
    private PdfGenerator $pdf_generator;
    private InvoiceFileStorage $file_storage;
    private LoggerInterface $logger;

    public function __construct(
        InvoiceRepository $repo,
        InvoiceItemRepository $invoice_item_repo,
        PaymentRepository $payment_repo,
        InvoicePdfRenderer $pdf_renderer,
        PdfGenerator $pdf_generator,
        InvoiceFileStorage $file_storage,
        ?LoggerInterface $logger = null
    ) {
        $this->repo          = $repo;
        $this->invoice_item_repo = $invoice_item_repo;
        $this->payment_repo  = $payment_repo;
        $this->pdf_renderer  = $pdf_renderer;
        $this->pdf_generator = $pdf_generator;
        $this->file_storage  = $file_storage;
        $this->logger        = $logger ?? new NullLogger();
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
            'ItemType' => $normalized_type !== '' ? $normalized_type : 'charge',
            'Description' => sanitize_text_field($description),
            'Quantity' => 1,
            'UnitPrice' => $amount,
            'TaxRate' => 0,
            'TaxAmount' => 0,
            'TotalAmount' => $amount,
            'DisplayOrder' => $this->invoice_item_repo->get_next_display_order($invoice_id),
        ];

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

    public function get_with_customers(?string $start_date = null, ?string $end_date = null): ?array {
        return $this->repo->get_all_with_customers($start_date, $end_date);
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

    public function get_detail_for_portal( mixed $id, mixed $customer_id, bool $is_client_admin = false): ?array {
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

    public function get_status_label(string $status, ?array $invoice = null): string {
        $normalized_status = sanitize_key($status);

        if ($normalized_status === 'paid' && $invoice !== null && $this->has_deposit_items($invoice)) {
            return 'Paid with deposit';
        }

        return ucwords(str_replace('-', ' ', $normalized_status));
    }

    public function settle_deposit(int $invoice_id, string $outcome): bool|WP_Error {
        $outcome = sanitize_key($outcome);
        if (!in_array($outcome, self::VALID_DEPOSIT_OUTCOMES, true)) {
            return new WP_Error('validation', __('Invalid deposit outcome.', 'my-village-hall'));
        }

        $invoice = $this->get_detail($invoice_id);
        if (!$invoice) {
            return new WP_Error('not_found', __('Invoice not found', 'my-village-hall'));
        }

        if (($invoice['Status'] ?? '') !== 'paid') {
            return new WP_Error('validation', __('Deposit can only be settled when the invoice is fully paid.', 'my-village-hall'));
        }

        if (!$this->has_deposit_items($invoice)) {
            return new WP_Error('validation', __('No unsettled deposit found on this invoice.', 'my-village-hall'));
        }

        $deposit_total = $this->get_deposit_total($invoice);
        if ($deposit_total <= 0.0) {
            return new WP_Error('validation', __('Deposit amount must be greater than zero.', 'my-village-hall'));
        }

        $next_item_type = $outcome === 'refunded' ? 'deposit-refunded' : 'deposit-retained';
        $retyped = $this->invoice_item_repo->update_item_type_for_invoice($invoice_id, 'deposit', $next_item_type);
        if (!$retyped) {
            return new WP_Error('database', __('Failed to update deposit item status.', 'my-village-hall'));
        }

        $total_amount = round((float) ($invoice['TotalAmount'] ?? 0), 2);
        $sub_total = round((float) ($invoice['SubTotal'] ?? 0), 2);
        $amount_paid = round((float) ($invoice['AmountPaid'] ?? 0), 2);

        if ($outcome === 'refunded') {
            $refund_created = $this->payment_repo->create([
                'InvoiceId' => $invoice_id,
                'PaymentDate' => current_time('Y-m-d'),
                'Amount' => round($deposit_total * -1, 2),
                'PaymentMethod' => 'refund',
                'TransactionReference' => null,
                'Notes' => __('Deposit refunded', 'my-village-hall'),
            ]);

            if ($refund_created === false) {
                return new WP_Error('database', __('Failed to create refund payment.', 'my-village-hall'));
            }

            $total_amount = max(0.0, round($total_amount - $deposit_total, 2));
            $sub_total = max(0.0, round($sub_total - $deposit_total, 2));
            $amount_paid = round($this->payment_repo->get_total_by_invoice($invoice_id), 2);
        }

        $amount_due = max(0.0, round($total_amount - $amount_paid, 2));

        return $this->repo->update([
            'SubTotal' => $sub_total,
            'TotalAmount' => $total_amount,
            'AmountPaid' => $amount_paid,
            'AmountDue' => $amount_due,
            'Status' => 'paid',
        ], ['Id' => $invoice_id]);
    }

    public function has_deposit_items(array $invoice): bool {
        $items = $invoice['Items'] ?? [];
        if (!is_array($items) || $items === []) {
            return false;
        }

        foreach ($items as $item) {
            if (sanitize_key((string) ($item['ItemType'] ?? '')) === 'deposit') {
                return true;
            }
        }

        return false;
    }

    public function get_deposit_total(array $invoice): float {
        $items = $invoice['Items'] ?? [];
        if (!is_array($items) || $items === []) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($items as $item) {
            if (sanitize_key((string) ($item['ItemType'] ?? '')) !== 'deposit') {
                continue;
            }

            $total += (float) ($item['TotalAmount'] ?? 0);
        }

        return round($total, 2);
    }

    private function build_booking_summary(array $items): array {
        $bookings = [];

        foreach ($items as $item) {
            $booking_id = \intval($item['BookingId'] ?? 0);
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

            $bookings[$booking_id]['TotalAmount'] += \floatval($item['TotalAmount'] ?? 0);
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

        if (!isset($data['total_amount']) || \floatval($data['total_amount']) < 0) {
            return new WP_Error('validation', __('Valid total amount is required', 'my-village-hall'));
        }

        if (empty($data['invoice_id'])) {
            $invoice_number_formatted = $this->get_next_invoice_number();
        } else {
            $invoice_number_formatted = $data['invoice_number'];
        }

        $total_amount = \floatval($data['total_amount']);
        $amount_paid = \floatval($data['amount_paid'] ?? 0);
        $requested_status = sanitize_key($data['status'] ?? 'draft');

        $record = [
            'InvoiceNumber' => $invoice_number_formatted,
            'CustomerId' => \intval($data['customer_id']),
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
            'SubTotal' => \floatval($data['sub_total'] ?? 0),
            'TaxAmount' => \floatval($data['tax_amount'] ?? 0),
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
            $invoice_id = \intval($data['invoice_id']);

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
            $this->logger->warning('Invoice number is nearing maximum for prefix', [
                'prefix' => $prefix,
                'next_number' => $invoice_number,
            ]);

            //TODO: We should ideally have a better strategy for handling this scenario, such as allowing admin to set a new prefix and/or archiving old invoices. For now, we'll just append a '1' to the prefix to allow continued invoicing, but this is not a long-term solution.
            if ($invoice_number === 999999) {
                $this->logger->error('Maximum invoice number reached for prefix', [
                    'prefix' => $prefix,
                    'next_number' => $invoice_number,
                ]);
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

    public function get_for_portal( mixed $customer_id, mixed $statuses = [], ?string $start_date = null, ?string $end_date = null): ?array {
        return $this->repo->get_for_customer_portal($customer_id, $statuses, $start_date, $end_date);
    }

    public function update_status( mixed $id, mixed $status): bool|WP_Error {
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

    /**
     * Return the site logo as a base64-encoded data URI, or an empty string
     * if no custom logo is set or the file cannot be read.
     * Dompdf requires images to be embedded when isRemoteEnabled is false.
     */
    private function get_site_logo_data_uri(): string {
        if (!function_exists('get_theme_mod')) {
            return '';
        }

        $logo_id = (int) get_theme_mod('custom_logo');
        if (!$logo_id) {
            return '';
        }

        $path = get_attached_file($logo_id);
        if (!$path || !is_readable($path)) {
            return '';
        }

        $mime = mime_content_type($path);
        if (!$mime || !str_starts_with($mime, 'image/')) {
            return '';
        }

        $data = file_get_contents($path); // phpcs:ignore WordPress.WP.AlternativeFunctions
        if ($data === false) {
            return '';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($data);
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

        // 2a. Embed site logo as a base64 data URI (Dompdf has isRemoteEnabled=false).
        $invoice['SiteLogoDataUri'] = $this->get_site_logo_data_uri();

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
