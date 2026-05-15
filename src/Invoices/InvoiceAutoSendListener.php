<?php

declare(strict_types=1);

namespace MYVH\Invoices;

use MYVH\Email\EmailService;

if (!defined('ABSPATH')) {
    exit;
}

class InvoiceAutoSendListener {

    private InvoiceService $invoice_service;
    private EmailService $email_service;

    public function __construct(InvoiceService $invoice_service, ?EmailService $email_service = null) {
        $this->invoice_service = $invoice_service;
        $this->email_service = $email_service ?: new EmailService();
    }

    public function register(): void {
        add_action('myvh_invoice_generated', [$this, 'handle_invoice_generated'], 10, 3);
    }

    public function handle_invoice_generated($invoice_id, $bookings = [], $options = []): void {
        $invoice_id = \intval($invoice_id);

        if ($invoice_id <= 0) {
            return;
        }

        if (!myvh_setting('invoicing.auto_send', true)) {
            return;
        }

        $invoice = $this->invoice_service->get_detail($invoice_id);
        if (empty($invoice)) {
            error_log(sprintf('MYVH Invoice Auto Send: Invoice #%d not found.', $invoice_id));
            return;
        }

        $recipient = $this->resolve_recipient($invoice);
        if ($recipient === '') {
            error_log(sprintf('MYVH Invoice Auto Send: No recipient for invoice #%d.', $invoice_id));
            return;
        }

        $pdf_path = $this->invoice_service->generate_pdf($invoice_id);
        if (is_wp_error($pdf_path)) {
            error_log(sprintf(
                'MYVH Invoice Auto Send: PDF generation failed for invoice #%d: %s',
                $invoice_id,
                $pdf_path->get_error_message()
            ));
            return;
        }

        $template_vars = $this->build_template_vars($invoice_id, $invoice, is_array($bookings) ? $bookings : []);

        $send_result = $this->email_service->send([
            'to' => $recipient,
            'template' => 'invoice',
            'template_vars' => $template_vars,
            'attachments' => [$pdf_path],
        ]);

        if (!$send_result) {
            error_log(sprintf('MYVH Invoice Auto Send: Email send failed for invoice #%d.', $invoice_id));
            return;
        }

        $status_result = $this->invoice_service->update_status($invoice_id, 'sent');
        if (is_wp_error($status_result) || $status_result === false) {
            $message = is_wp_error($status_result)
                ? $status_result->get_error_message()
                : 'Unknown error while updating status.';

            error_log(sprintf(
                'MYVH Invoice Auto Send: Failed to mark invoice #%d as sent: %s',
                $invoice_id,
                $message
            ));
        }
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

    private function build_template_vars(int $invoice_id, array $invoice, array $bookings): array {
        $branding = $this->email_service->get_branding();
        $invoice_url = '';

        $pdf_url = $this->invoice_service->get_invoice_pdf_url($invoice_id);
        if (!is_wp_error($pdf_url)) {
            $invoice_url = $pdf_url;
        }

        return array_merge($branding, [
            'customer_name' => (string) ($invoice['BillingName'] ?? $invoice['CustomerName'] ?? ''),
            'customer_address' => $this->build_address_line($invoice),
            'invoice_ref' => (string) ($invoice['InvoiceNumber'] ?? ('INV-' . $invoice_id)),
            'invoice_total' => number_format((float) ($invoice['TotalAmount'] ?? 0), 2, '.', ''),
            'invoice_due_date' => (string) ($invoice['DueDate'] ?? ''),
            'invoice_status' => $this->invoice_service->get_status_label('sent'),
            'invoice_url' => $invoice_url,
            'organisation_name' => (string) ($invoice['BillingOrganisationName'] ?? $invoice['OrganisationName'] ?? ''),
            'booking_details' => $this->build_booking_details($invoice, $bookings),
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

    private function build_booking_details(array $invoice, array $bookings): string {
        $booking_count = \intval($invoice['BookingCount'] ?? count($bookings));
        if ($booking_count <= 0) {
            return '';
        }

        if ($booking_count === 1 && !empty($bookings[0]['Description'])) {
            return (string) $bookings[0]['Description'];
        }

        return sprintf('Includes %d booking(s).', $booking_count);
    }
}