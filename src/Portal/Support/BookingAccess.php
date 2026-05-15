<?php
namespace MYVH\Portal\Support;

use MYVH\Bookings\BookingService;
use MYVH\Organisations\OrganisationMemberRepository;

class BookingAccess {

    public static function get_accessible_booking(
        int $booking_id,
        int $customer_id,
        bool $is_client_admin,
        BookingService $booking_service,
        ?OrganisationMemberRepository $organisation_member_repo = null
    ) {
        if ($booking_id <= 0) {
            return null;
        }

        if ($is_client_admin) {
            return $booking_service->get_by_id_with_details($booking_id);
        }

        $booking = $booking_service->get_by_id_with_details($booking_id);

        if (!$booking) {
            return null;
        }

        if (intval($booking['CustomerId'] ?? 0) === $customer_id) {
            return $booking;
        }

        $organisation_id = \intval($booking['OrganisationId'] ?? 0);
        if (
            $customer_id > 0
            && $organisation_id > 0
            && $organisation_member_repo
            && $organisation_member_repo->is_customer_admin($organisation_id, $customer_id)
        ) {
            return $booking;
        }

        return null;
    }
}