<?php

namespace MYVH\AutoInvoicing;

use MYVH\Invoices\InvoiceGeneratorService;
use MYVH\Bookings\BookingService;
use DateTime;
/**
 * Single Booking Auto-Invoicing
 *
 * Handles automatic invoice generation for single (non-recurring) bookings based on configured rules.
 */
class SingleBookingAutoInvoicing {

    protected InvoiceGeneratorService $invoice_generator_service;
    protected BookingService $booking_service;
    protected SingleBookingAutoInvoiceRuleRepository $rule_repository;

    public function __construct(InvoiceGeneratorService $invoice_generator_service,
                                BookingService $booking_service,
                                SingleBookingAutoInvoiceRuleRepository $rule_repository) {
        $this->invoice_generator_service = $invoice_generator_service;
        $this->booking_service = $booking_service;
        $this->rule_repository = $rule_repository;
    }

    /**
     * Main method to evaluate and generate invoice for a single booking
     *
     */
    public function process() : int {
        $invoice_count = 0;

        $bookings = $this->get_bookings_for_invoicing();
        if (empty($bookings)) {
            return $invoice_count;
        }

        $rule_buckets = $this->filter_bookings_by_trigger_condition($bookings);

        foreach ($rule_buckets as $bucket) {
            $invoice_count += $this->generate_invoice($bucket['bookings'], $bucket['rule']);
        }

        return $invoice_count;
    }

    /**
     * Check if the trigger condition for auto-invoicing is met based on settings
     *
     * @param array $booking The booking array to evaluate
     * @return bool True if the trigger condition is met, false otherwise
     */
    protected function is_trigger_condition_met($booking, array $rule): bool {
        // Manual invoicing rules never trigger auto-invoicing.
        if (($rule['trigger_timing'] ?? 'confirmation') === 'manual_invoicing') {
            return false;
        }
        if (($rule['trigger_timing'] ?? 'confirmation') === 'confirmation') {
            return $booking['Status'] === 'confirmed';
        }
        if (($rule['trigger_timing'] ?? 'confirmation') === 'booking_date') {
            $booking_date = new DateTime($booking['StartDate']);
            $now = new DateTime();
            return $booking_date <= $now;
        }
        if (($rule['trigger_timing'] ?? 'confirmation') === 'days_before_booking_date') {
            $booking_date = new DateTime($booking['StartDate']);
            $now = new DateTime();
            $interval = $booking_date->diff($now);

            return $interval->invert === 0 || ($interval->invert === 1 && $interval->days >= intval($rule['trigger_offset_days'] ?? 0));
        }
        if (($rule['trigger_timing'] ?? 'confirmation') === 'days_after_booking_date') {
            $booking_date = new DateTime($booking['StartDate']);
            $now = new DateTime();
            $interval = $booking_date->diff($now);

            return $interval->invert === 0 && $interval->days >= intval($rule['trigger_offset_days'] ?? 0);
        }

        return false;
    }

    protected function generate_invoice(array $bookings, array $rule): int {
        $booking_ids = $this->get_booking_ids_from_bookings($bookings);

        if (empty($booking_ids)) {
            return 0;
        }

        $rule_id = intval($rule['id'] ?? 0);

        $created = $this->invoice_generator_service->generate_invoices_from_bookings($booking_ids,
            [
                'rule_id' => $rule_id,
                // Keep group_by for immediate use in grouping before the service loop;
                // the service will re-resolve it from the table via rule_id.
                'group_by' => $this->normalize_key($rule['group_by'] ?? 'per_booking'),
            ]
        );

        if (is_wp_error($created) || !is_array($created)) {
            return 0;
        }

        return count($created);
    }

    protected function get_bookings_for_invoicing() : array {
        // Gets the list of single bookings that need to be invoiced
        return $this->booking_service->get_uninvoiced_single_bookings();
    }

    protected function filter_bookings_by_trigger_condition(array $bookings) : array {
        $filtered_bookings = [];

        foreach ($bookings as $booking) {
            $rule = $this->resolve_rule_for_booking($booking);

            if (empty($rule['enabled'])) {
                continue;
            }

            if (!$this->is_trigger_condition_met($booking, $rule)) {
                continue;
            }

            $bucket_key = sprintf(
                '%d|%s|%d',
                intval($rule['id'] ?? 0),
                $this->normalize_key($rule['group_by'] ?? 'per_booking'),
                intval($rule['due_date_offset_days'] ?? 30)
            );

            if (!isset($filtered_bookings[$bucket_key])) {
                $filtered_bookings[$bucket_key] = [
                    'rule' => $rule,
                    'bookings' => [],
                ];
            }

            $filtered_bookings[$bucket_key]['bookings'][] = $booking;
        }

        return $filtered_bookings;
    }

    protected function resolve_rule_for_booking(array $booking): array {
        $active_rules = [];
        foreach ($this->rule_repository->get_active_rules() as $rule) {
            $active_rules[intval($rule['Id'])] = $this->map_rule_record($rule);
        }

        $customer_rule_id = intval($booking['CustomerSingleBookingAutoInvoiceRuleId'] ?? 0);
        if ($customer_rule_id > 0 && isset($active_rules[$customer_rule_id])) {
            return $active_rules[$customer_rule_id];
        }

        $organisation_rule_id = intval($booking['OrganisationSingleBookingAutoInvoiceRuleId'] ?? 0);
        if ($organisation_rule_id > 0 && isset($active_rules[$organisation_rule_id])) {
            return $active_rules[$organisation_rule_id];
        }

        $default_rule_id = intval(myvh_setting('invoicing.single_default_rule_id', 0));
        if ($default_rule_id > 0 && isset($active_rules[$default_rule_id])) {
            return $active_rules[$default_rule_id];
        }

        if (!empty($active_rules)) {
            return reset($active_rules);
        }

        return [
            'id' => 0,
            'enabled' => (bool) myvh_setting('invoicing.single_enabled', false),
            'trigger_timing' => $this->normalize_key(myvh_setting('invoicing.single_trigger_timing', 'confirmation')),
            'trigger_offset_days' => max(0, intval(myvh_setting('invoicing.single_trigger_offset_days', 0))),
            'group_by' => $this->normalize_key(myvh_setting('invoicing.single_group_by', 'per_booking')),
            'due_date_offset_days' => max(0, intval(myvh_setting('invoicing.single_due_date_offset_days', 30))),
        ];
    }

    protected function map_rule_record(array $rule): array {
        return [
            'id' => intval($rule['Id'] ?? 0),
            'enabled' => !empty($rule['IsActive']),
            'trigger_timing' => $this->normalize_key($rule['TriggerTiming'] ?? 'confirmation'),
            'trigger_offset_days' => max(0, intval($rule['TriggerOffsetDays'] ?? 0)),
            'group_by' => $this->normalize_key($rule['GroupBy'] ?? 'per_booking'),
            'due_date_offset_days' => max(0, intval($rule['DueDateOffsetDays'] ?? 30)),
        ];
    }

    protected function normalize_key($value): string {
        $value = strtolower((string) $value);

        return preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '';
    }

    protected function get_booking_ids_from_bookings(array $bookings) : array {
        // Utility method to extract booking IDs from booking objects

        return array_map(function($booking) {
            return $booking['Id'];
        }, $bookings);
    }

}