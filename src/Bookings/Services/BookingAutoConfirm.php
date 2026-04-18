<?php

namespace MYVH\Bookings\Services;

use MYVH\Bookings\BookingRepository;
use MYVH\Customers\CustomerRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Bookings\BookingStatus;
use MYVH\Bookings\Booking;

class BookingAutoConfirm
{
    private $booking_repo;
    private $customer_repo;
    private $organisation_repo;

    public function __construct(BookingRepository $booking_repo,
                                CustomerRepository $customer_repo,
                                OrganisationRepository $organisation_repo)
    {
        $this->booking_repo = $booking_repo;
        $this->customer_repo = $customer_repo;
        $this->organisation_repo = $organisation_repo;
    }

    public function auto_confirm($booking_id) : string
    {
        // TODO: Auto-confirm logic can be implemented here, e.g. based on booking date or other criteria
        // For now, we'll just return the booking with status set to confirmed
        //$booking['Status'] = self::CONFIRMED;
        $booking = $this->booking_repo->get($booking_id);

        $org = $this->organisation_repo->get_by_id($booking->organisationId());
        $customer = $this->customer_repo->get_by_id($booking->customerId());

        if ($org['AllowAutoConfirm'] || $customer['AllowAutoConfirm']) {
               $this->booking_repo->update(['Status' => BookingStatus::CONFIRMED->value], ['Id' => $booking_id]);
             do_action('myvh_event_booking.confirmed', ['booking_id' => $booking_id]);

        }

           return $booking->status()->value;
    }
}