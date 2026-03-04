<?php
if (!defined('ABSPATH')) exit;

class MYVH_Invoice_Controller {

    private $service;

    public function __construct($service) {
        $this->service = $service;
    }

    public function save() {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_invoice');

        $this->service->save($_POST);

        wp_redirect(admin_url('admin.php?page=myvh-invoices&updated=1'));
        exit;
    }

    public function delete() {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_invoice');

        $id = intval($_GET['id']);
        $this->service->delete($id);

        wp_redirect(admin_url('admin.php?page=myvh-invoices&deleted=1'));
        exit;
    }

    public function update_status() {

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

    public function record_payment() {

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
