<?php
if (!defined('ABSPATH')) exit;

class MYVH_Customer_Controller {

    private $service;

    public function __construct($service) {
        $this->service = $service;
    }

    public function save() {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_customer');

        $this->service->save($_POST);

        wp_redirect(admin_url('admin.php?page=myvh-customers&updated=1'));
        exit;
    }

    public function delete() {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_customer');

        $id = intval($_GET['id']);
        $this->service->delete($id);

        wp_redirect(admin_url('admin.php?page=myvh-customers&deleted=1'));
        exit;
    }
}
