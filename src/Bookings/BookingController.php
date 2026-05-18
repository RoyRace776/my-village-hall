<?php

namespace MYVH\Bookings;

class BookingController
{
    private $service;
    private $request_validator;

    public function __construct(
        BookingService $service,
        BookingRequestValidator $request_validator
    ) {
        $this->service = $service;
        $this->request_validator = $request_validator;
    }

    public function save(): void
    {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_booking');

        $posted_data = wp_unslash($_POST);
        $data = SaveBookingRequest::from_post($posted_data);

        $validation_result = $this->request_validator->validate($data);
        if (is_wp_error($validation_result)) {
            set_transient($this->get_form_transient_key(), $posted_data, 120);
            wp_redirect($this->get_form_redirect_url($data, $validation_result->get_error_message()));
            exit;
        }

        $result = $this->service->save($data);

        if (is_wp_error($result)) {
            set_transient($this->get_form_transient_key(), $posted_data, 120);
            wp_redirect($this->get_form_redirect_url($data, $result->get_error_message()));
            exit;
        }

        foreach ($this->service->get_last_warnings() as $warning) {
            \MYVH\Admin\AdminNotices::warning($warning);
        }

        delete_transient($this->get_form_transient_key());

        wp_redirect($this->get_success_redirect_url($data));
        exit;
    }

    private function get_form_transient_key(): string
    {
        return 'myvh_booking_form_' . get_current_user_id();
    }

    private function get_form_redirect_url( mixed $data, mixed $error_message): string
    {
        $query_args = [
            'page' => 'my-village-hall',
            'error' => $error_message,
        ];

        if (!empty($data['booking_id'])) {
            $query_args['edit'] = \intval($data['booking_id']);
        } else {
            $query_args['add'] = 1;
        }

        if (!empty($data['return_to'])) {
            $query_args['return_to'] = wp_validate_redirect($data['return_to'], '');
        }

        return add_query_arg($query_args, admin_url('admin.php'));
    }

    private function get_success_redirect_url($data): string
    {
        $return_to = !empty($data['return_to'])
            ? wp_validate_redirect($data['return_to'], '')
            : '';

        if ($return_to) {
            return $return_to;
        }

        return admin_url('admin.php?page=my-village-hall&updated=1');
    }

    public function cancel(): void
    {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        // Only read booking ID after nonce validation
        check_admin_referer('myvh_cancel_booking');

        $id = isset($_POST['id']) ? \intval($_POST['id']) : (isset($_GET['id']) ? \intval($_GET['id']) : 0);
        if ($id <= 0) {
            wp_die(__('Invalid booking ID', 'my-village-hall'));
        }
        $this->service->cancel($id);

        wp_redirect(admin_url('admin.php?page=my-village-hall&updated=1'));
        exit;
    }
}
