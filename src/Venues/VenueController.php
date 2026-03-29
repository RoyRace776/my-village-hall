<?php
namespace MYVH\Venues;

if (!defined('ABSPATH')) exit;

class VenueController {

    private $service;
    private $request_validator;

    public function __construct(
        VenueService $service,
        VenueRequestValidator $request_validator
    ) {
        $this->service = $service;
        $this->request_validator = $request_validator;
    }

    public function save(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_venue');

        $data = SaveVenueRequest::from_post(wp_unslash($_POST));

        $validation_result = $this->request_validator->validate($data);
        if (is_wp_error($validation_result)) {
            wp_redirect(admin_url('admin.php?page=myvh-venues&error=' . urlencode($validation_result->get_error_message())));
            exit;
        }

        $result = $this->service->save($data);

        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=myvh-venues&error=' . urlencode($result->get_error_message())));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=myvh-venues&updated=1'));
        exit;
    }

    public function delete(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_venue');

        $id = intval($_GET['id']);
        $this->service->delete($id);

        wp_redirect(admin_url('admin.php?page=myvh-venues&deleted=1'));
        exit;
    }
}