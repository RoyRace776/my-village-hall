<?php
if (!defined('ABSPATH')) exit;

class MYVH_Addon_Controller {

    private $service;

    public function __construct($service) {
        $this->service = $service;
    }

    public function save() {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_addon');

        $this->service->save($_POST);

        wp_redirect(admin_url('admin.php?page=myvh-addons&updated=1'));
        exit;
    }

    public function delete() {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_addon');

        $id = intval($_GET['id']);
        $this->service->delete($id);

        wp_redirect(admin_url('admin.php?page=myvh-addons&deleted=1'));
        exit;
    }
}
