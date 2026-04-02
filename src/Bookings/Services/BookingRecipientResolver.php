<?php
namespace MYVH\Bookings\Services;

if (!defined('ABSPATH')) exit;

use MYVH\Bookings\BookingRepository;

class BookingRecipientResolver
{
    private $booking_repo;
    public function __construct()
    {
        global $myvh_container;
        $booking_repo = $myvh_container->get(BookingRepository::class);
        $this->booking_repo = $booking_repo;
    }

    public function resolve(int $booking_id): string
    {
        $booking = $this->booking_repo->get_by_id($booking_id);

        // TODO: Put processing logic here to determine the correct recipient based on the booking details, e.g. customer email, or admin email if no customer email is available
        $recipient = "nobody@nowhere.com";

        return $recipient;
    }
}