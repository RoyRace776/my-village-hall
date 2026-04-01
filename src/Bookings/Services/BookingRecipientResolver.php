<?php
namespace MYVH\Bookings\Services;

if (!defined('ABSPATH')) exit;

use MYVH\Bookings\BookingService;

class BookingRecipientResolver
{
    private $booking_service;
    public function __construct()
    {
        global $myvh_container;
        $booking_service = $myvh_container->get(BookingService::class);
        $this->booking_service = $booking_service;
    }

    public function resolve(int $booking_id): string
    {
        $booking = $this->booking_service->get_by_id($booking_id);

        // TODO: Put processing logic here to determine the correct recipient based on the booking details, e.g. customer email, or admin email if no customer email is available
        $recipient = "nobody@nowhere.com";

        return $recipient;
    }
}