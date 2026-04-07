<?php
namespace MYVH\Portal\Actions;

use MYVH\Bookings\BookingService;
use MYVH\Customers\CustomerService;
use MYVH\Portal\Support\BookingAccess;
use MYVH\Portal\ClientAdminService;

use Exception;

class UpdateBookingAction {

    public function __construct(
        private BookingService $booking_service,
        private CustomerService $customer_service,
        private ClientAdminService $client_admin_service
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

        $booking = BookingAccess::get_accessible_booking(
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
            'edit_scope'  => sanitize_text_field($input['edit_scope'] ?? ''),
            'customer_id' => (int) $booking['CustomerId'],
            'organisation_id' => (int) ($booking['OrganisationId'] ?? 0),
            'room_id'     => (int) $booking['RoomId'],
            'start_date'  => sanitize_text_field($input['start_date'] ?? $booking['StartDate']),
            'end_date'    => sanitize_text_field($booking['EndDate'] ?? $booking['StartDate']),
            'start_time'  => sanitize_text_field($input['start_time'] ?? substr($booking['StartTime'], 0, 5)),
            'end_time'    => sanitize_text_field($input['end_time'] ?? substr($booking['EndTime'], 0, 5)),
            'description' => sanitize_textarea_field($input['description'] ?? $booking['Description']),
            'status'      => sanitize_text_field($booking['Status'] ?? ''),
            'public'      => !empty($booking['Public']) ? 1 : 0,
            'no_invoice_required' => $is_admin
                ? intval($input['no_invoice_required'] ?? ($booking['NoInvoiceRequired'] ?? 0))
                : intval($booking['NoInvoiceRequired'] ?? 0),
        ]);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
    }
}