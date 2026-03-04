<?php

class MYVH_Booking_Controller {

    private $service;

    public function __construct($service) {
        $this->service = $service;
    }

    public function create() {

        if (!current_user_can('manage_myvh')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('myvh_create_booking');

        $result = $this->service->create_booking($_POST);

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        wp_redirect(admin_url('admin.php?page=myvh-bookings&created=1'));
        exit;
    }
    
    public function save() {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_booking');

        $this->service->save($_POST);

        wp_redirect(admin_url('admin.php?page=my-village-hall&updated=1'));
        exit;
    }

    public function cancel() {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_cancel_booking');

        $id = intval($_GET['id']);
        $this->service->cancel($id);

        wp_redirect(admin_url('admin.php?page=my-village-hall&updated=1'));
        exit;
    }

}