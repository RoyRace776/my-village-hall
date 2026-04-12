<?php
namespace MYVH\Bookings\Services;

if (!defined('ABSPATH')) exit;

use MYVH\Bookings\BookingRepository;
use MYVH\Customers\CustomerRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Bookings\Booking;

class BookingRecipientResolver
{
    private $booking_repo;
    private $customer_repo;
    private $organisation_repo;
    public function __construct()
    {
        global $myvh_container;
        $booking_repo = $myvh_container->get(BookingRepository::class);
        $customer_repo = $myvh_container->get(CustomerRepository::class);
        $organisation_repo = $myvh_container->get(OrganisationRepository::class);
        $this->booking_repo = $booking_repo;
        $this->customer_repo = $customer_repo;
        $this->organisation_repo = $organisation_repo;
    }

    public function resolve_for_booking(int $booking_id): string
    {
        $recipient = '';

        try {
            $booking = $this->booking_repo->get($booking_id);
        } catch (\Throwable $e) {
            return (string) get_option('admin_email');
        }

        $organisation_id = (int) $booking->organisationId();
        if ($organisation_id > 0) {
            $organisation = $this->organisation_repo->get_by_id($organisation_id);
            if (!empty($organisation['SendBookingEmailsToOrganisation']) && !empty($organisation['ContactEmail'])) {
                $organisation_email = sanitize_email((string) $organisation['ContactEmail']);
                if ($organisation_email !== '' && is_email($organisation_email)) {
                    return $organisation_email;
                }
            }
        }

        if (!empty($booking->customerId())) {
            $customer = $this->customer_repo->get_by_id($booking->customerId());
            if ($customer && !empty($customer['Email'])) {
                $customer_email = sanitize_email((string) $customer['Email']);
                if ($customer_email !== '' && is_email($customer_email)) {
                    return $customer_email;
                }
            }
        }

        return (string) get_option('admin_email');
    }

    public function resolve_for_invoice(int $invoice_id): string
    {
        // TODO: Put processing logic here to handle organisations
        $recipient = "not_set@nowhere.com";


        return $recipient;
    }
}