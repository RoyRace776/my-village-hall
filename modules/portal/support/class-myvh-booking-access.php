<?php
class MYVH_Booking_Access {

    public static function get_accessible_booking(
        int $booking_id,
        int $customer_id,
        bool $is_client_admin,
        MYVH_Booking_Service $booking_service
    ) {
        if ($booking_id <= 0) {
            return null;
        }

        if ($is_client_admin) {
            return $booking_service->get_by_id_with_details($booking_id);
        }

        return $booking_service->get_by_id_with_details_for_customer($booking_id, $customer_id);
    }
}