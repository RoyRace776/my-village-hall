<?php
namespace MYVH\Invoices;

if (!defined('ABSPATH')) exit;

class InvoiceController {

    private $service;
    private $request_validator;

    public function __construct(
        InvoiceService $service,
        InvoiceRequestValidator $request_validator
    ) {
        $this->service = $service;
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
            wp_redirect(admin_url('admin.php?page=myvh-invoices&error=' . urlencode($validation_result->get_error_message())));
            exit;
        }

        $result = $this->service->save($data);
        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=myvh-invoices&error=' . urlencode($result->get_error_message())));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=myvh-invoices&updated=1'));
        exit;
    }

    public function delete(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_invoice');

        $id = intval($_GET['id']);
        $this->service->delete($id);

        wp_redirect(admin_url('admin.php?page=myvh-invoices&deleted=1'));
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

        wp_redirect(admin_url('admin.php?page=myvh-invoices&updated=1'));
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

        wp_redirect(admin_url('admin.php?page=myvh-invoices&updated=1'));
        exit;
    }
}
