<?php

namespace MYVH\AutoInvoicing;

use MYVH\Settings\AutoInvoicingSettings;
use MYVH\Invoices\InvoiceGeneratorService;
use MYVH\Bookings\BookingService;
use DateTime;
/**
 * Single Booking Auto-Invoicing
 *
 * Handles automatic invoice generation for single (non-recurring) bookings based on configured rules.
 */
class SingleBookingAutoInvoicing {

    protected $invoice_generator_service;
    protected $booking_service;

    public function __construct(InvoiceGeneratorService $invoice_generator_service,
                                BookingService $booking_service) {
        $this->invoice_generator_service = $invoice_generator_service;
        $this->booking_service = $booking_service;
    }

    /**
     * Main method to evaluate and generate invoice for a single booking
     *
     * @param array $booking The booking array to evaluate
     */
    public function process() : int {
        // Check if auto-invoicing for single bookings is enabled
        $invoice_count = 0;
        if (!myvh_setting('invoicing.single_enabled')) {
            return $invoice_count; // Auto-invoicing for single bookings is disabled so no invoices generated
        }

        $bookings = $this->get_bookings_for_invoicing();
        if ($bookings) {
            $bookings_to_invoice = $this->filter_bookings_by_trigger_condition($bookings);
        }

        if ($bookings && $bookings_to_invoice) {
            // Generate the invoice for these bookings
            $this->generate_invoice($bookings_to_invoice);
            $invoice_count++;
        }

        return $invoice_count;
    }

    /**
     * Check if the trigger condition for auto-invoicing is met based on settings
     *
     * @param array $booking The booking array to evaluate
     * @return bool True if the trigger condition is met, false otherwise
     */
    protected function is_trigger_condition_met($booking) {
        // Implementation of trigger condition evaluation based on settings
        // This will involve checking the booking status, dates, and comparing with current date/time
        // according to the selected trigger timing (e.g., confirmation, booking date, days before/after)
        if (myvh_setting('invoicing.single_trigger_timing') === 'confirmation') {
            return $booking['Status'] === 'confirmed';
        }
        if (myvh_setting('invoicing.single_trigger_timing') === 'booking_date') {
            $booking_date = new DateTime($booking['StartDate']);
            $now = new DateTime();
            return $booking_date <= $now;
        }
        if (myvh_setting('invoicing.single_trigger_timing') === 'days_before_booking_date') {
            $booking_date = new DateTime($booking['StartDate']);
            $now = new DateTime();
            $interval = $booking_date->diff($now);
            //invert is 1 if booking date is in the future, and we want to check if it's at least the offset days away
            //if it's in the past (invert 0), then we want to trigger
            return $interval->invert === 0 || ($interval->invert === 1 && $interval->days >= myvh_setting('invoicing.single_trigger_offset_days'));
        }
        if (myvh_setting('invoicing.single_trigger_timing') === 'days_after_booking_date') {
            $booking_date = new DateTime($booking['StartDate']);
            $now = new DateTime();
            $interval = $booking_date->diff($now);
            // only trigger if booking date is in the past (invert 0) and it's at least the offset days ago
            return $interval->invert === 0 && $interval->days >= myvh_setting('invoicing.single_trigger_offset_days');
        }

        return false;
    }

    protected function generate_invoice($bookings) {
        // Implementation of invoice generation logic for the given bookings
        // This will involve creating an invoice record, populating it with booking details,
        // and saving it to the database or sending it to an external invoicing system.

        //We should take into account the grouping settings here as well, so if grouping is enabled we should group the bookings accordingly before generating the invoice
        $booking_ids = $this->get_booking_ids_from_bookings($bookings);

        $grouping = myvh_setting('invoicing.single_group_by');

        $this->invoice_generator_service->generate_invoices_from_bookings($booking_ids,
                                                                            ['group_by' => $grouping]);
    }

    protected function get_bookings_for_invoicing() : array {
        // Gets the list of single bookings that need to be invoiced
        return $this->booking_service->get_uninvoiced_single_bookings();
    }

    protected function filter_bookings_by_trigger_condition(array $bookings) : array {
        // Filters the given bookings based on the trigger conditions defined in settings
        $filtered_bookings = [];
        foreach ($bookings as $booking) {
            if ($this->is_trigger_condition_met($booking)) {
                $filtered_bookings[] = $booking;
            }
        }

        return $filtered_bookings;
    }

    protected function get_booking_ids_from_bookings(array $bookings) : array {
        // Utility method to extract booking IDs from booking objects

        return array_map(function($booking) {
            return $booking['Id'];
        }, $bookings);
    }

}