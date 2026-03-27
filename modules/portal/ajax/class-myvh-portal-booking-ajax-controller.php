<?php

class MYVH_Portal_Booking_Ajax_Controller {

public function __construct(
    private MYVH_Get_Booking_Action $get_action,
    private MYVH_Update_Booking_Action $update_action,
    private MYVH_Delete_Booking_Action $delete_action
) {}

public function register(): void {
    add_action('wp_ajax_myvh_portal_get_booking', [$this, 'get']);
    add_action('wp_ajax_myvh_portal_update_booking', [$this, 'update']);
    add_action('wp_ajax_myvh_portal_delete_booking', [$this, 'delete']);
}

public function get(): void {
    MYVH_Portal_Auth::require_user();

    try {
        $booking_id = intval($_REQUEST['booking_id'] ?? 0);
        $booking = $this->get_action->execute($booking_id);

        wp_send_json_success(['booking' => $booking]);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage(), 400);
    }
}

public function update(): void {
    MYVH_Portal_Auth::require_user();

    try {
        $this->update_action->execute($_POST);
        wp_send_json_success(['message' => 'Booking updated']);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage(), 400);
    }
}

public function delete(): void {
    MYVH_Portal_Auth::require_user();

    try {
        $booking_id = intval($_POST['booking_id'] ?? 0);
        $this->delete_action->execute($booking_id);

        wp_send_json_success(['message' => 'Booking deleted']);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage(), 400);
    }
}
}