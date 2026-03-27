<?php
class MYVH_Update_Booking_Action {

    public function __construct(
        private MYVH_Booking_Service $booking_service,
        private MYVH_Customer_Service $customer_service,
        private MYVH_Client_Admin_Service $client_admin_service
    ) {}

    public function execute(array $input): void {

        $customer = $this->customer_service->get_by_user_id(get_current_user_id());
        $is_admin = $this->client_admin_service->can_administer_blog(
            get_current_user_id(),
            get_current_blog_id()
        );

        if (empty($customer['Id']) && !$is_admin) {
            throw new Exception('Customer profile not found');
        }

        $booking_id = intval($input['booking_id'] ?? 0);
        if ($booking_id <= 0) {
            throw new Exception('Booking ID is required');
        }

        $booking = MYVH_Booking_Access::get_accessible_booking(
            $booking_id,
            (int) ($customer['Id'] ?? 0),
            $is_admin,
            $this->booking_service
        );

        if (!$booking) {
            throw new Exception('Booking not found');
        }

        $result = $this->booking_service->save([
            'booking_id'  => $booking_id,
            'customer_id' => (int) $booking['CustomerId'],
            'room_id'     => (int) $booking['RoomId'],
            'start_date'  => sanitize_text_field($input['start_date'] ?? $booking['StartDate']),
            'start_time'  => sanitize_text_field($input['start_time'] ?? substr($booking['StartTime'], 0, 5)),
            'end_time'    => sanitize_text_field($input['end_time'] ?? substr($booking['EndTime'], 0, 5)),
            'description' => sanitize_textarea_field($input['description'] ?? $booking['Description']),
        ]);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
    }
}