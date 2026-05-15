<?php
namespace MYVH\Invoices;

use MYVH\Email\EmailService;

if (!defined('ABSPATH')) exit;

class InvoiceController {

    private $service;
    private $generator_service;
    private $request_validator;
    private $email_service;

    public function __construct(
        InvoiceService $service,
        InvoiceGeneratorService $generator_service,
        InvoiceRequestValidator $request_validator,
        ?EmailService $email_service = null
    ) {
        $this->service = $service;
        $this->generator_service = $generator_service;
        $this->request_validator = $request_validator;
        $this->email_service = $email_service ?: new EmailService();
    }

    public function save(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_invoice');

        $data = SaveInvoiceRequest::from_post(wp_unslash($_POST));

        $validation_result = $this->request_validator->validate($data);
        if (is_wp_error($validation_result)) {
            $this->redirect_with_message($this->get_admin_page_slug('myvh-invoices'), 'error', $validation_result->get_error_message());
            exit;
        }

        $is_new = empty($data['invoice_id']);

        $result = $this->service->save($data);
        if (is_wp_error($result)) {
            $this->redirect_with_message($this->get_admin_page_slug('myvh-invoices'), 'error', $result->get_error_message());
            exit;
        }

        if ($is_new) {
            $pdf_result = $this->service->generate_pdf((int) $result);
            if (is_wp_error($pdf_result)) {
                $this->redirect_with_message(
                    $this->get_admin_page_slug('myvh-invoices'),
                    'error',
                    sprintf(
                        __('Invoice created but PDF generation failed: %s', 'my-village-hall'),
                        $pdf_result->get_error_message()
                    )
                );
                exit;
            }
        }

        $this->redirect_with_message($this->get_admin_page_slug('myvh-invoices'), 'updated', '1');
        exit;
    }

    public function generate_from_bookings(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_generate_invoices');

        $booking_ids_raw = wp_unslash($_POST['booking_ids'] ?? []);
        if (is_string($booking_ids_raw)) {
            $booking_ids_raw = explode(',', $booking_ids_raw);
        }

        $booking_ids = array_filter(array_map('intval', (array) $booking_ids_raw));
        if (empty($booking_ids)) {
            $this->redirect_with_message('myvh-invoice-generate', 'error', __('Select at least one booking to generate invoices.', 'my-village-hall'));
            exit;
        }

        $group_by = sanitize_key($_POST['group_by'] ?? 'per_booking');
        if (!in_array($group_by, ['per_booking', 'by_customer', 'by_organisation'], true)) {
            $group_by = 'per_booking';
        }

        $result = $this->generator_service->generate_invoices_from_bookings($booking_ids, [
            'group_by' => $group_by,
            'trigger_event' => 'manual',
            'origin' => 'admin',
        ]);

        if (is_wp_error($result)) {
            $this->redirect_with_message('myvh-invoice-generate', 'error', $result->get_error_message());
            exit;
        }

        foreach ($result as $invoice_id) {
            $pdf_result = $this->service->generate_pdf((int) $invoice_id);
            if (is_wp_error($pdf_result)) {
                $this->redirect_with_message(
                    'myvh-invoices',
                    'error',
                    sprintf(
                        __('Invoice(s) created but PDF generation failed for #%d: %s', 'my-village-hall'),
                        (int) $invoice_id,
                        $pdf_result->get_error_message()
                    )
                );
                exit;
            }
        }

        $this->redirect_with_message('myvh-invoices', 'generated', (string) count($result));
        exit;
    }

    public function view_pdf(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_view_invoice_pdf');

        $id = \intval($_REQUEST['id'] ?? 0);
        $result = $this->service->get_invoice_pdf_url($id);

        if (is_wp_error($result)) {
            $this->redirect_with_message(
                $this->get_admin_page_slug('myvh-invoices'),
                'error',
                $result->get_error_message(),
                $this->get_admin_redirect_args()
            );
            exit;
        }

        wp_safe_redirect($result);
        exit;
    }

    public function email_invoice(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_email_invoice');

        $id = \intval($_REQUEST['id'] ?? $_REQUEST['invoice_id'] ?? 0);
        if ($id <= 0) {
            $this->redirect_with_message(
                $this->get_admin_page_slug('myvh-invoices'),
                'error',
                __('Invalid invoice ID.', 'my-village-hall'),
                $this->get_admin_redirect_args()
            );
            exit;
        }

        $invoice = $this->service->get_detail($id);
        if (empty($invoice)) {
            $this->redirect_with_message(
                $this->get_admin_page_slug('myvh-invoices'),
                'error',
                __('Invoice not found.', 'my-village-hall'),
                $this->get_admin_redirect_args()
            );
            exit;
        }

        $recipient = $this->resolve_recipient($invoice);
        if ($recipient === '') {
            $this->redirect_with_message(
                $this->get_admin_page_slug('myvh-invoices'),
                'error',
                __('No valid recipient email found for this invoice.', 'my-village-hall'),
                $this->get_admin_redirect_args()
            );
            exit;
        }

        $pdf_path = $this->service->generate_pdf($id);
        if (is_wp_error($pdf_path)) {
            $this->redirect_with_message(
                $this->get_admin_page_slug('myvh-invoices'),
                'error',
                $pdf_path->get_error_message(),
                $this->get_admin_redirect_args()
            );
            exit;
        }

        $send_result = $this->email_service->send([
            'to' => $recipient,
            'template' => 'invoice',
            'template_vars' => $this->build_template_vars($id, $invoice),
            'attachments' => [$pdf_path],
        ]);

        if (!$send_result) {
            $this->redirect_with_message(
                $this->get_admin_page_slug('myvh-invoices'),
                'error',
                __('Failed to send invoice email.', 'my-village-hall'),
                $this->get_admin_redirect_args()
            );
            exit;
        }

        $status_result = $this->service->update_status($id, 'sent');
        if (is_wp_error($status_result) || $status_result === false) {
            $message = is_wp_error($status_result)
                ? $status_result->get_error_message()
                : __('Unknown error while updating invoice status.', 'my-village-hall');

            $this->redirect_with_message(
                $this->get_admin_page_slug('myvh-invoices'),
                'error',
                $message,
                $this->get_admin_redirect_args()
            );
            exit;
        }

        $this->redirect_with_message(
            $this->get_admin_page_slug('myvh-invoices'),
            'emailed',
            '1',
            $this->get_admin_redirect_args()
        );
        exit;
    }

    public function delete(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_invoice');

        $id = \intval($_REQUEST['id'] ?? 0);
        $result = $this->service->delete($id);

        if (is_wp_error($result)) {
            $this->redirect_with_message($this->get_admin_page_slug('myvh-invoices'), 'error', $result->get_error_message(), $this->get_admin_redirect_args());
            exit;
        }

        $this->redirect_with_message($this->get_admin_page_slug('myvh-invoices'), 'deleted', '1');
        exit;
    }

    public function update_status(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_update_invoice_status');

        $id = \intval($_REQUEST['id'] ?? $_REQUEST['invoice_id'] ?? 0);
        $status = sanitize_text_field($_REQUEST['status'] ?? '');

        $result = $this->service->update_status($id, $status);

        if (is_wp_error($result)) {
            $this->redirect_with_message($this->get_admin_page_slug('myvh-invoices'), 'error', $result->get_error_message(), $this->get_admin_redirect_args());
            exit;
        }

        $this->redirect_with_message($this->get_admin_page_slug('myvh-invoices'), 'updated', '1', $this->get_admin_redirect_args());
        exit;
    }

    public function record_payment(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_record_payment');

        $id = \intval($_POST['invoice_id'] ?? 0);
        $amount = \floatval($_POST['payment_amount'] ?? 0);

        $result = $this->service->record_payment($id, $amount);

        if (is_wp_error($result)) {
            $this->redirect_with_message($this->get_admin_page_slug('myvh-invoices'), 'error', $result->get_error_message(), $this->get_admin_redirect_args());
            exit;
        }

        $this->redirect_with_message($this->get_admin_page_slug('myvh-invoices'), 'updated', '1', $this->get_admin_redirect_args());
        exit;
    }

    private function get_admin_redirect_args(): array {
        $args = [];

        $redirect_view = \intval($_REQUEST['redirect_view'] ?? 0);
        if ($redirect_view > 0) {
            $args['view'] = $redirect_view;
        }

        return $args;
    }

    private function get_admin_page_slug(string $fallback): string {
        $page = sanitize_key($_REQUEST['redirect_page'] ?? $_REQUEST['page'] ?? $fallback);
        $allowed_pages = ['myvh-invoices', 'myvh-invoice-generate'];

        return in_array($page, $allowed_pages, true) ? $page : $fallback;
    }

    private function redirect_with_message(string $page, string $key, string $value, array $extra_args = []): void {
        $args = array_merge(['page' => $page, $key => $value], $extra_args);
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
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

    private function build_template_vars(int $invoice_id, array $invoice): array {
        $branding = $this->email_service->get_branding();
        $invoice_url = '';

        $pdf_url = $this->service->get_invoice_pdf_url($invoice_id);
        if (!is_wp_error($pdf_url)) {
            $invoice_url = $pdf_url;
        }

        return array_merge($branding, [
            'customer_name' => (string) ($invoice['BillingName'] ?? $invoice['CustomerName'] ?? ''),
            'customer_address' => $this->build_address_line($invoice),
            'invoice_ref' => (string) ($invoice['InvoiceNumber'] ?? ('INV-' . $invoice_id)),
            'invoice_total' => number_format((float) ($invoice['TotalAmount'] ?? 0), 2, '.', ''),
            'invoice_due_date' => (string) ($invoice['DueDate'] ?? ''),
            'invoice_status' => $this->service->get_status_label('sent'),
            'invoice_url' => $invoice_url,
            'organisation_name' => (string) ($invoice['BillingOrganisationName'] ?? $invoice['OrganisationName'] ?? ''),
            'booking_details' => $this->build_booking_details($invoice),
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

    private function build_booking_details(array $invoice): string {
        $bookings = is_array($invoice['BookingsSummary'] ?? null) ? $invoice['BookingsSummary'] : [];
        $booking_count = \intval($invoice['BookingCount'] ?? count($bookings));
        if ($booking_count <= 0) {
            return '';
        }

        $first_booking = $bookings[0] ?? [];
        if ($booking_count === 1 && !empty($first_booking['Description'])) {
            return (string) $first_booking['Description'];
        }

        return sprintf(__('Includes %d booking(s).', 'my-village-hall'), $booking_count);
    }
}
