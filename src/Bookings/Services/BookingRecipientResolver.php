<?php
namespace MYVH\Bookings\Services;

if (!defined('ABSPATH')) exit;

use MYVH\Bookings\BookingRepository;
use MYVH\Customers\CustomerRepository;
use MYVH\Bookings\Booking;

class BookingRecipientResolver
{
    private $booking_repo;
    private $customer_repo;
    public function __construct()
    {
        global $myvh_container;
        $booking_repo = $myvh_container->get(BookingRepository::class);
        $customer_repo = $myvh_container->get(CustomerRepository::class);
        $this->booking_repo = $booking_repo;
        $this->customer_repo = $customer_repo;
    }

    public function resolve(int $booking_id): string
    {
        $booking = $this->booking_repo->get($booking_id);

        // TODO: Put processing logic here to hande organisaitons
        $recipient = "";

        if ($booking && !empty($booking->customerId())) {
            $customer = $this->customer_repo->get_by_id($booking->customerId());
            if ($customer && !empty($customer['Email'])) {
                $recipient = $customer['Email'];
            } else {
                // Fallback to admin email if no customer email is available
                $recipient = get_option('admin_email');
            }
        } else {
            // Fallback to admin email if no customer email is available
            $recipient = get_option('admin_email');
        }

        return $recipient;
    }
}