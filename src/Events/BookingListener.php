<?php
namespace MYVH\Events;

use MYVH\Bookings\Services\BookingAutoConfirm;
use MYVH\Email\EmailService;
use MYVH\Bookings\Services\BookingRecipientResolver;

class BookingListener {
    private $email_service;

    public function __construct($email_service = null) {
        $this->email_service = $email_service ?: new EmailService();
    }

    public function register(): void {
        add_action(
            'myvh_event_booking.created',
            [$this, 'handle_booking_created']
        );

        add_action(
            'myvh_event_booking.confirmed',
            [$this, 'handle_booking_confirmed']
        );

        add_action(
            'myvh_event_booking.cancelled',
            [$this, 'handle_booking_cancelled']
        );

        add_action(
            'myvh_event_booking.updated',
            [$this, 'handle_booking_updated']
        );
    }

    public function handle_booking_created($payload): void {

        $booking_id = $payload['booking_id'];

        // If the booking is suitable for autoconfirm, then confirm it immediately
        //(new BookingStatus())->auto_confirm($booking_id);
        global $myvh_container;
        $booking_auto_confirm = $myvh_container->get(BookingAutoConfirm::class);
        $booking_auto_confirm->auto_confirm($booking_id);
    }

    public function handle_booking_confirmed($payload): void {

        $booking_id = $payload['booking_id'];

        $email_resolver = new BookingRecipientResolver();
        $email = $email_resolver->resolve($booking_id);
        $this->email_service->send([
            'to' => $email,
            'subject' => 'Booking confirmed',
            'template' => 'booking-confirmed',
            'template_vars' => [

            ],
        ]);
    }

    public function handle_booking_cancelled($payload): void {

        $booking_id = $payload['booking_id'];

        // Processing here
    }

    public function handle_booking_updated($payload): void {

        $booking_id = $payload['booking_id'];

        // Processing here
    }
}