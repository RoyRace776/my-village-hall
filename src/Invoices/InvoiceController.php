<?php
namespace MYVH\Invoices;

if (!defined('ABSPATH')) exit;

class InvoiceController {

    private $service;
    private $generator_service;
    private $request_validator;

    public function __construct(
        InvoiceService $service,
        InvoiceGeneratorService $generator_service,
        InvoiceRequestValidator $request_validator
    ) {
        $this->service = $service;
        $this->generator_service = $generator_service;
        $this->request_validator = $request_validator;
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

        $result = $this->service->save($data);
        if (is_wp_error($result)) {
            $this->redirect_with_message($this->get_admin_page_slug('myvh-invoices'), 'error', $result->get_error_message());
            exit;
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

        $this->redirect_with_message('myvh-invoices', 'generated', (string) count($result));
        exit;
    }

    public function delete(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_invoice');

        $id = intval($_GET['id']);
        $this->service->delete($id);

        $this->redirect_with_message($this->get_admin_page_slug('myvh-invoices'), 'deleted', '1');
        exit;
    }

    public function update_status(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_update_invoice_status');

        $id = intval($_GET['id']);
        $status = sanitize_text_field($_GET['status']);

        $this->service->update_status($id, $status);

        $this->redirect_with_message($this->get_admin_page_slug('myvh-invoices'), 'updated', '1');
        exit;
    }

    public function record_payment(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_record_payment');

        $id = intval($_POST['invoice_id']);
        $amount = floatval($_POST['payment_amount']);

        $this->service->record_payment($id, $amount);

        $this->redirect_with_message($this->get_admin_page_slug('myvh-invoices'), 'updated', '1');
        exit;
    }

    private function get_admin_page_slug(string $fallback): string {
        $page = sanitize_key($_REQUEST['redirect_page'] ?? $_REQUEST['page'] ?? $fallback);
        $allowed_pages = ['myvh-invoices', 'myvh-invoice-generate'];

        return in_array($page, $allowed_pages, true) ? $page : $fallback;
    }

    private function redirect_with_message(string $page, string $key, string $value): void {
        wp_redirect(admin_url('admin.php?page=' . $page . '&' . $key . '=' . urlencode($value)));
    }
}
