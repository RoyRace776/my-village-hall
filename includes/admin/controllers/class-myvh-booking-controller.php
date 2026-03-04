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
}