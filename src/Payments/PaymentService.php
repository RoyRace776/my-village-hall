<?php
namespace MYVH\Payments;

use MYVH\Invoices\InvoiceService;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentService {
    public const VALID_METHODS = ['cash', 'card', 'cheque', 'transfer', 'other'];

    private PaymentRepository $repo;
    private InvoiceService $invoice_service;

    public function __construct(PaymentRepository $repo, InvoiceService $invoice_service) {
        $this->repo = $repo;
        $this->invoice_service = $invoice_service;
    }

    public function get_valid_methods(): array {
        return self::VALID_METHODS;
    }

    public function get_method_label(string $method): string {
        return ucwords(str_replace('-', ' ', $method));
    }

    public function get_payments(int $invoice_id = 0): array {
        return $this->repo->get_with_invoice_details($invoice_id);
    }

    public function get_for_invoice(int $invoice_id): array {
        return $this->repo->get_by_invoice($invoice_id);
    }

    public function create(array $data): int|WP_Error {
        $invoice_id = \intval($data['invoice_id'] ?? 0);
        $amount = round((float) ($data['payment_amount'] ?? 0), 2);
        $method = sanitize_key($data['payment_method'] ?? '');
        $payment_date = sanitize_text_field($data['payment_date'] ?? current_time('Y-m-d'));
        $reference = sanitize_text_field($data['payment_reference'] ?? '');
        $comment = sanitize_textarea_field($data['payment_comment'] ?? '');

        if ($invoice_id <= 0) {
            return new WP_Error('validation', __('A valid invoice is required.', 'my-village-hall'));
        }

        if ($amount <= 0) {
            return new WP_Error('validation', __('Payment amount is required.', 'my-village-hall'));
        }

        if (!in_array($method, self::VALID_METHODS, true)) {
            return new WP_Error('validation', __('Payment type is required.', 'my-village-hall'));
        }

        if (!$this->is_valid_date($payment_date)) {
            return new WP_Error('validation', __('Payment date must be a valid date.', 'my-village-hall'));
        }

        $invoice = $this->invoice_service->get($invoice_id);
        if (!$invoice) {
            return new WP_Error('not_found', __('Invoice not found.', 'my-village-hall'));
        }

        $amount_due = (float) ($invoice['AmountDue'] ?? 0);
        if ($amount_due <= 0) {
            return new WP_Error('validation', __('This invoice has already been fully paid.', 'my-village-hall'));
        }

        if ($amount - $amount_due > 0.00001) {
            return new WP_Error('validation', __('Payment amount cannot exceed the outstanding balance.', 'my-village-hall'));
        }

        return $this->invoice_service->record_payment(
            $invoice_id,
            $amount,
            $method,
            $payment_date,
            $reference,
            $comment
        );
    }

    public function delete(int $payment_id): bool|WP_Error {
        if ($payment_id <= 0) {
            return new WP_Error('validation', __('A valid payment is required.', 'my-village-hall'));
        }

        return $this->invoice_service->delete_payment($payment_id);
    }

    private function is_valid_date(string $value): bool {
        if ($value === '') {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }
}
