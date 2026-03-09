<?php
if (!defined('ABSPATH')) exit;

class MYVH_Room_Rate_Controller {

    private $service;

    public function __construct($service) {
        $this->service = $service;
    }

    public function save() {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_rate');

        $result = $this->service->save($_POST);

        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=myvh-room-rates&add=1&error=' . urlencode($result->get_error_message())));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=myvh-room-rates&updated=1'));
        exit;
    }

    public function delete() {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_rate');

        $id = intval($_GET['id']);
        $this->service->delete($id);

        wp_redirect(admin_url('admin.php?page=myvh-room-rates&deleted=1'));
        exit;
    }
}
