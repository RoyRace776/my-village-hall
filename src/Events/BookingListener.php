<?php
namespace MYVH\Events;

use MYVH\Bookings\Services\BookingAutoConfirm;
use MYVH\Bookings\BookingService;
use MYVH\Customers\CustomerService;
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
        $send_confirmation_email = isset($payload['send_confirmation_email']) && \intval($payload['send_confirmation_email']) === 0
            ? 0
            : 1;

        // If the booking is suitable for autoconfirm, then confirm it immediately
        //(new BookingStatus())->auto_confirm($booking_id);
        global $myvh_container;
        $booking_auto_confirm = $myvh_container->get(BookingAutoConfirm::class);
        $booking_auto_confirm->auto_confirm($booking_id, $send_confirmation_email);
    }

    public function handle_booking_confirmed($payload): void {
        if (isset($payload['send_confirmation_email']) && \intval($payload['send_confirmation_email']) === 0) {
            return;
        }

        $booking_id = $payload['booking_id'];
        $email = $this->resolve_email($booking_id);
        $template_vars = $this->get_booking_template_vars($booking_id);

        $this->email_service->send([
            'to' => $email,
            'subject' => 'Booking confirmed',
            'template' => 'booking-confirmed',
            'template_vars' => $template_vars,
        ]);
    }

    public function handle_booking_cancelled($payload): void {
        $booking_id = $payload['booking_id'];
        $email = $this->resolve_email($booking_id);
        $template_vars = $this->get_booking_template_vars($booking_id);

        $this->email_service->send([
            'to' => $email,
            'subject' => 'Booking cancelled',
            'template' => 'booking-cancelled',
            'template_vars' => $template_vars,
        ]);
    }

    protected function resolve_email(int $booking_id): string {
        $email_resolver = new BookingRecipientResolver();
        return $email_resolver->resolve_for_booking($booking_id);
    }

    protected function get_booking_template_vars(int $booking_id): array {
        global $myvh_container;
        $booking_service = $myvh_container->get(BookingService::class);
        $customer_service = $myvh_container->get(CustomerService::class);

        $booking = $booking_service->get_by_id_with_details($booking_id);
        $customer = !empty($booking['CustomerId'])
            ? $customer_service->get_by_id((int) $booking['CustomerId'])
            : [];

        $booking_date = (string) ($booking['StartDate'] ?? '');
        if ($booking_date !== '') {
            $timestamp = strtotime($booking_date);
            if ($timestamp) {
                $booking_date = date_i18n(get_option('date_format'), $timestamp);
            }
        }

        $start_time = (string) ($booking['StartTime'] ?? '');
        $end_time = (string) ($booking['EndTime'] ?? '');
        $booking_time = trim($start_time . ($end_time !== '' ? ' - ' . $end_time : ''));

        $customer_address_parts = array_filter([
            (string) ($customer['AddressLine1'] ?? ''),
            (string) ($customer['PostCode'] ?? ''),
        ]);

        $branding = $this->email_service->get_branding();
        $booking_amount = $this->resolve_booking_amount($booking_service, $booking_id, $booking);

        return [
            'customer_name' => (string) ($booking['CustomerName'] ?? ''),
            'customer_address' => implode(', ', $customer_address_parts),
            'booking_ref' => '#' . (int) ($booking['Id'] ?? $booking_id),
            'booking_description' => (string) ($booking['Description'] ?? ''),
            'booking_date' => $booking_date,
            'booking_time' => $booking_time,
            'venue_name' => (string) ($booking['VenueName'] ?? ''),
            'room_name' => (string) ($booking['RoomName'] ?? ''),
            'booking_amount' => $booking_amount,
            'logo_url' => (string) ($branding['logo_url'] ?? ''),
            'site_name' => (string) ($branding['site_name'] ?? ''),
            'site_url' => (string) ($branding['site_url'] ?? ''),
        ];
    }

    protected function resolve_booking_amount(BookingService $booking_service, int $booking_id, array $booking): string {
        $charges = $booking_service->get_charges_for_booking($booking_id);
        $charge_total = 0.0;
        $addons = $booking_service->get_addons_for_booking($booking_id);
        $addon_total = 0.0;

        foreach ($charges as $charge) {
            $charge_total += (float) ($charge['TotalAmount'] ?? 0);
        }

        foreach ($addons as $addon) {
            $addon_total += (float) ($addon['TotalAmount'] ?? 0);
        }

        $booking_total = $charge_total + $addon_total;

        if ($booking_total > 0.0) {
            return number_format($booking_total, 2, '.', '');
        }

        $rate = $booking['Rate'] ?? '';
        if ($rate === '' || $rate === null) {
            return '';
        }

        return number_format((float) $rate, 2, '.', '');
    }

    public function handle_booking_updated($payload): void {

        $booking_id = $payload['booking_id'];

        // Processing here
    }
}