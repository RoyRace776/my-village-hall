<?php
if (!defined('ABSPATH')) exit;

class MYVH_Room_Controller {

    private $service;

    public function __construct(
        MYVH_Room_Service $service,
    ) {
        $this->service = $service;
    }

    public function save() {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_room');

        $data = wp_unslash($_POST);

        $result = $this->service->save($data);

        if (is_wp_error($result)) {

            foreach ($result->get_error_messages() as $msg) {
                MYVH_Admin_Notices::error($msg);
            }

            wp_redirect(admin_url('admin.php?page=myvh-rooms'));
            exit;
        }

        MYVH_Admin_Notices::success(__('Room saved successfully', 'my-village-hall'));

        wp_redirect(admin_url('admin.php?page=myvh-rooms&updated=1'));
        exit;
    }

    public function delete() {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_room');

        $id = intval($_GET['id']);

        $result = $this->service->delete($id);

        if (is_wp_error($result)) {

            foreach ($result->get_error_messages() as $msg) {
                MYVH_Admin_Notices::error($msg);
            }

            wp_redirect(admin_url('admin.php?page=myvh-rooms'));
            exit;
        }

        MYVH_Admin_Notices::success(__('Room deleted', 'my-village-hall'));

        wp_redirect(admin_url('admin.php?page=myvh-rooms&deleted=1'));
        exit;
    }
}