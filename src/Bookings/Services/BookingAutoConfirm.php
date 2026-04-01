<?php

namespace MYVH\Bookings\Services;

use MYVH\Bookings\BookingService;
use MYVH\Customers\CustomerService;
use MYVH\Organisations\OrganisationService;
use MYVH\Bookings\BookingStatus;

class BookingAutoConfirm
{
    private $booking_service;
    private $customer_service;
    private $organisation_service;

    public function __construct(BookingService $booking_service,
                                CustomerService $customer_service,
                                OrganisationService $organisation_service)
    {
        $this->booking_service = $booking_service;
        $this->customer_service = $customer_service;
        $this->organisation_service = $organisation_service;
    }

    public function auto_confirm($booking_id) : string
    {
        // TODO: Auto-confirm logic can be implemented here, e.g. based on booking date or other criteria
        // For now, we'll just return the booking with status set to confirmed
        //$booking['Status'] = self::CONFIRMED;
        $booking = $this->booking_service->get_by_id($booking_id);

        $org = $this->organisation_service->get_by_id($booking['OrganisationId']);
        $customer = $this->customer_service->get_by_id($booking['CustomerId']);

        if ($org['AllowAutoConfirm'] || $customer['AllowAutoConfirm']) {
             $this->booking_service->update_status($booking_id, BookingStatus::CONFIRMED);
             do_action('myvh_event_booking.confirmed', ['booking_id' => $booking_id]);

        }

        return $booking['Status'];
    }
}