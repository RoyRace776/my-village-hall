<?php
class MYVH_Delete_Booking_Action {

public function __construct(
    private MYVH_Booking_Service $booking_service,
    private MYVH_Customer_Service $customer_service,
    private MYVH_Client_Admin_Service $client_admin_service
) {}

public function execute(int $booking_id): void {

    $customer = $this->customer_service->get_by_user_id(get_current_user_id());
    $is_admin = $this->client_admin_service->can_administer_blog(
        get_current_user_id(),
        get_current_blog_id()
    );

    $booking = MYVH_Booking_Access::get_accessible_booking(
        $booking_id,
        (int) ($customer['Id'] ?? 0),
        $is_admin,
        $this->booking_service
    );

    if (!$booking) {
        throw new Exception('Booking not found');
    }

    $rules = $this->booking_service->can_delete($booking);

    if (empty($rules['can_delete'])) {
        throw new Exception($rules['reason'] ?? 'Cannot delete booking');
    }

    $result = $this->booking_service->delete($booking_id);

    if (is_wp_error($result)) {
        throw new Exception($result->get_error_message());
    }
}
}