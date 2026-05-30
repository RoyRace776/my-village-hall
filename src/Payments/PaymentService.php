<?php
namespace MYVH\Payments;

use MYVH\Email\EmailService;
use MYVH\Invoices\InvoiceService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentService {
    public const VALID_METHODS = ['cash', 'card', 'cheque', 'transfer', 'other'];

    private PaymentRepository $repo;
    private InvoiceService $invoice_service;
    private EmailService $email_service;
    private LoggerInterface $logger;

    public function __construct(PaymentRepository $repo, InvoiceService $invoice_service, ?EmailService $email_service = null, ?LoggerInterface $logger = null) {
        $this->repo = $repo;
        $this->invoice_service = $invoice_service;
        $this->email_service = $email_service ?: new EmailService();
        $this->logger = $logger ?? new NullLogger();
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

    public function send_receipt(int $payment_id): bool|WP_Error {
        if ($payment_id <= 0) {
            return new WP_Error('validation', __('A valid payment is required.', 'my-village-hall'));
        }

        $payment = $this->repo->get_by_id($payment_id);
        if (!is_array($payment)) {
            return new WP_Error('not_found', __('Payment not found.', 'my-village-hall'));
        }

        $invoice_id = (int) ($payment['InvoiceId'] ?? 0);
        if ($invoice_id <= 0) {
            return new WP_Error('validation', __('Payment is not linked to a valid invoice.', 'my-village-hall'));
        }

        $invoice = $this->invoice_service->get_detail($invoice_id);
        if (!is_array($invoice) || $invoice === []) {
            return new WP_Error('not_found', __('Invoice not found.', 'my-village-hall'));
        }

        $recipient = $this->resolve_recipient($invoice);
        if ($recipient === '') {
            return new WP_Error('validation', __('No valid recipient email found for this invoice.', 'my-village-hall'));
        }

        $sent = $this->email_service->send([
            'to' => $recipient,
            'template' => 'payment-receipt',
            'template_vars' => $this->build_receipt_template_vars($payment_id, $payment, $invoice),
        ]);

        if (!$sent) {
            return new WP_Error('email_failed', __('Payment saved, but the receipt email could not be sent.', 'my-village-hall'));
        }

        return true;
    }

    private function is_valid_date(string $value): bool {
        if ($value === '') {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private function resolve_recipient(array $invoice): string {
        $billing_email = sanitize_email((string) ($invoice['BillingEmail'] ?? ''));
        if ($billing_email !== '' && is_email($billing_email)) {
            return $billing_email;
        }

        $customer_email = sanitize_email((string) ($invoice['CustomerEmail'] ?? ''));
        if ($customer_email !== '' && is_email($customer_email)) {
            return $customer_email;
        }

        return '';
    }

    private function build_receipt_template_vars(int $payment_id, array $payment, array $invoice): array {
        $branding = $this->email_service->get_branding();
        $payment_date = (string) ($payment['PaymentDate'] ?? '');
        $payment_date_formatted = $payment_date;

        try {
            if ($payment_date !== '') {
                $payment_date_formatted = (new \DateTimeImmutable($payment_date))->format('j M Y');
            }
        } catch (\Exception $e) {
            $payment_date_formatted = $payment_date;
        }

        $invoice_status = (string) ($invoice['Status'] ?? '');
        $invoice_status_label = $invoice_status !== ''
            ? $this->invoice_service->get_status_label($invoice_status, $invoice)
            : '';

        return array_merge($branding, [
            'customer_name' => (string) ($invoice['BillingName'] ?? $invoice['CustomerName'] ?? ''),
            'customer_address' => $this->build_address_line($invoice),
            'invoice_ref' => (string) ($invoice['InvoiceNumber'] ?? ('INV-' . $invoice['Id'])),
            'invoice_total' => number_format((float) ($invoice['TotalAmount'] ?? 0), 2, '.', ''),
            'invoice_due_date' => (string) ($invoice['DueDate'] ?? ''),
            'invoice_status' => (string) $invoice_status_label,
            'organisation_name' => (string) ($invoice['BillingOrganisationName'] ?? $invoice['OrganisationName'] ?? ''),
            'payment_id' => (string) $payment_id,
            'payment_amount' => number_format((float) ($payment['Amount'] ?? 0), 2, '.', ''),
            'payment_date' => $payment_date_formatted,
            'payment_method' => $this->get_method_label((string) ($payment['PaymentMethod'] ?? 'other')),
            'payment_reference' => (string) ($payment['TransactionReference'] ?? ''),
            'payment_comment' => (string) ($payment['Notes'] ?? ''),
            'amount_due' => number_format((float) ($invoice['AmountDue'] ?? 0), 2, '.', ''),
        ]);
    }

    private function build_address_line(array $invoice): string {
        $parts = array_filter([
            (string) ($invoice['BillingAddressLine1'] ?? ''),
            (string) ($invoice['BillingAddressLine2'] ?? ''),
            (string) ($invoice['BillingTownCity'] ?? ''),
            (string) ($invoice['BillingPostcode'] ?? ''),
        ]);

        return implode(', ', $parts);
    }
}
