<?php
namespace MYVH\Pricing;

if (!defined('ABSPATH')) exit;

class RoomRateController {

    private $service;
    private $request_validator;

    public function __construct(
        RoomRateService $service,
        RoomRateRequestValidator $request_validator
    ) {
        $this->service = $service;
        $this->request_validator = $request_validator;
    }

    public function save(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_rate');

        $data = SaveRoomRateRequest::from_post(wp_unslash($_POST));
        $room_context = !empty($data['room_id']) ? '&room_id=' . \intval($data['room_id']) : '';

        $validation_result = $this->request_validator->validate($data);
        if (is_wp_error($validation_result)) {
            wp_redirect(admin_url('admin.php?page=myvh-room-rates&add=1' . $room_context . '&error=' . urlencode($validation_result->get_error_message())));
            exit;
        }

        $result = $this->service->save($data);

        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=myvh-room-rates&add=1' . $room_context . '&error=' . urlencode($result->get_error_message())));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=myvh-room-rates&updated=1' . $room_context));
        exit;
    }

    public function delete(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_rate');

        $id = \intval($_GET['id']);
        $this->service->delete($id);

        wp_redirect(admin_url('admin.php?page=myvh-room-rates&deleted=1'));
        exit;
    }
}