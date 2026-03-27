<?php
if (!defined('ABSPATH')) exit;

class Addon_Controller {

    private $service;
    private $request_validator;

    public function __construct(
        Addon_Service $service,
        Addon_Request_Validator $request_validator
    ) {
        $this->service = $service;
        $this->request_validator = $request_validator;
    }

    public function save(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_addon');

        $data = Save_Addon_Request::from_post(wp_unslash($_POST));

        $validation_result = $this->request_validator->validate($data);
        if (is_wp_error($validation_result)) {
            wp_redirect(admin_url('admin.php?page=myvh-addons&error=' . urlencode($validation_result->get_error_message())));
            exit;
        }

        $result = $this->service->save($data);
        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=myvh-addons&error=' . urlencode($result->get_error_message())));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=myvh-addons&updated=1'));
        exit;
    }

    public function delete(): void {

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
