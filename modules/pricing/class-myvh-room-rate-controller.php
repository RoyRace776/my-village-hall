<?php
if (!defined('ABSPATH')) exit;

class Room_Rate_Controller {

    private $service;
    private $request_validator;

    public function __construct(
        Room_Rate_Service $service,
        Room_Rate_Request_Validator $request_validator
    ) {
        $this->service = $service;
        $this->request_validator = $request_validator;
    }

    public function save(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_rate');

        $data = Save_Room_Rate_Request::from_post(wp_unslash($_POST));

        $validation_result = $this->request_validator->validate($data);
        if (is_wp_error($validation_result)) {
            wp_redirect(admin_url('admin.php?page=myvh-room-rates&add=1&error=' . urlencode($validation_result->get_error_message())));
            exit;
        }

        $result = $this->service->save($data);

        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=myvh-room-rates&add=1&error=' . urlencode($result->get_error_message())));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=myvh-room-rates&updated=1'));
        exit;
    }

    public function delete(): void {
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
