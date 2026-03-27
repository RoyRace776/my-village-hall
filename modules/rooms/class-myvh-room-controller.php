<?php
if (!defined('ABSPATH')) exit;

class Room_Controller {

    private $service;
    private $request_validator;

    public function __construct(
        Room_Service $service,
        Room_Request_Validator $request_validator
    ) {
        $this->service = $service;
        $this->request_validator = $request_validator;
    }

    public function save(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_room');

        $posted_data = wp_unslash($_POST);
        $data = Save_Room_Request::from_post($posted_data);

        $validation_result = $this->request_validator->validate($data);
        if (is_wp_error($validation_result)) {
            set_transient($this->get_form_transient_key(), $posted_data, 120);

            foreach ($validation_result->get_error_messages() as $msg) {
                Admin_Notices::error($msg);
            }

            $redirect = !empty($data['room_id'])
                ? admin_url('admin.php?page=myvh-rooms&edit=' . intval($data['room_id']))
                : admin_url('admin.php?page=myvh-rooms&add=1');

            wp_redirect($redirect);
            exit;
        }

        $result = $this->service->save($data);

        if (is_wp_error($result)) {

            set_transient($this->get_form_transient_key(), $posted_data, 120);

            foreach ($result->get_error_messages() as $msg) {
                Admin_Notices::error($msg);
            }

            $redirect = !empty($data['room_id'])
                ? admin_url('admin.php?page=myvh-rooms&edit=' . intval($data['room_id']))
                : admin_url('admin.php?page=myvh-rooms&add=1');

            wp_redirect($redirect);
            exit;
        }

        delete_transient($this->get_form_transient_key());

        Admin_Notices::success(__('Room saved successfully', 'my-village-hall'));

        wp_redirect(admin_url('admin.php?page=myvh-rooms&updated=1'));
        exit;
    }

    private function get_form_transient_key() {
        return 'myvh_room_form_' . get_current_user_id();
    }

    public function delete(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_room');

        $id = intval($_GET['id']);

        $result = $this->service->delete($id);

        if (is_wp_error($result)) {

            foreach ($result->get_error_messages() as $msg) {
                Admin_Notices::error($msg);
            }

            wp_redirect(admin_url('admin.php?page=myvh-rooms'));
            exit;
        }

        Admin_Notices::success(__('Room deleted', 'my-village-hall'));

        wp_redirect(admin_url('admin.php?page=myvh-rooms&deleted=1'));
        exit;
    }
}