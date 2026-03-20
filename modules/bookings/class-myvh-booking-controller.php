<?php

class MYVH_Booking_Controller {

    private $service;

    public function __construct(MYVH_Booking_Service $service) {
        $this->service = $service;
    }

    public function save() {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_booking');

        $data = wp_unslash($_POST);

        // Filter addons: only keep rows where the user ticked the checkbox
        if (!empty($data['addons']) && is_array($data['addons'])) {
            $data['addons'] = array_values(array_filter($data['addons'], function($a) {
                return !empty($a['enabled']) && $a['enabled'] === '1' && !empty($a['addon_id']);
            }));
        } else {
            $data['addons'] = [];
        }

        $result = $this->service->save($data);

        if (is_wp_error($result)) {
            set_transient($this->get_form_transient_key(), $data, 120);
            wp_redirect($this->get_form_redirect_url($data, $result->get_error_message()));
            exit;
        }

        foreach ($this->service->get_last_warnings() as $warning) {
            MYVH_Admin_Notices::warning($warning);
        }

        delete_transient($this->get_form_transient_key());

        wp_redirect(admin_url('admin.php?page=my-village-hall&updated=1'));
        exit;
    }

    private function get_form_transient_key() {
        return 'myvh_booking_form_' . get_current_user_id();
    }

    private function get_form_redirect_url($data, $error_message) {
        $query_args = [
            'page' => 'my-village-hall',
            'error' => $error_message,
        ];

        if (!empty($data['booking_id'])) {
            $query_args['edit'] = intval($data['booking_id']);
        } else {
            $query_args['add'] = 1;
        }

        return add_query_arg($query_args, admin_url('admin.php'));
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