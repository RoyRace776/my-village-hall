<?php

namespace MYVH\AutoInvoicing;

use MYVH\Invoices\InvoiceGeneratorService;
use MYVH\Bookings\BookingService;
use DateTime;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
/**
 * Single Booking Auto-Invoicing
 *
 * Handles automatic invoice generation for single (non-recurring) bookings based on configured rules.
 */
class SingleBookingAutoInvoicing {

    protected InvoiceGeneratorService $invoice_generator_service;
    protected BookingService $booking_service;
    protected SingleBookingAutoInvoiceRuleRepository $rule_repository;
    protected RecurringBookingAutoInvoiceRuleRepository $recurring_rule_repository;
    protected LoggerInterface $logger;

    public function __construct(InvoiceGeneratorService $invoice_generator_service,
                                BookingService $booking_service,
                                SingleBookingAutoInvoiceRuleRepository $rule_repository,
                                RecurringBookingAutoInvoiceRuleRepository $recurring_rule_repository,
                                ?LoggerInterface $logger = null) {
        $this->invoice_generator_service = $invoice_generator_service;
        $this->booking_service = $booking_service;
        $this->rule_repository = $rule_repository;
        $this->recurring_rule_repository = $recurring_rule_repository;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Main method to evaluate and generate invoice for a single booking
     *
     */
    public function process() : int {
        $result = $this->process_with_result();

        return count($result['created_invoice_ids'] ?? []);
    }

    /**
     * Process single bookings and return a structured result for orchestration.
     *
     * @param array<int, array> $delegated_recurring_bookings
     * @return array{created_invoice_ids: array<int>}
     */
    public function process_with_result(array $delegated_recurring_bookings = []): array {
        $bookings = $this->get_bookings_for_invoicing($delegated_recurring_bookings);
        if (empty($bookings)) {
            return $this->empty_process_result();
        }

        $active_rules = $this->get_active_rules_by_id();
        $default_rule_id = \intval(myvh_setting('invoicing.single_default_rule_id', 0));

        $organisation_override_bookings = [];
        $remaining_after_organisation = [];

        foreach ($bookings as $booking) {
            if ($this->booking_has_non_default_organisation_rule($booking, $active_rules, $default_rule_id)) {
                $organisation_override_bookings[] = $booking;
                continue;
            }

            $remaining_after_organisation[] = $booking;
        }

        $customer_override_bookings = [];
        $default_rule_bookings = [];

        foreach ($remaining_after_organisation as $booking) {
            if ($this->booking_has_non_default_customer_rule($booking, $active_rules, $default_rule_id)) {
                $customer_override_bookings[] = $booking;
                continue;
            }

            $default_rule_bookings[] = $booking;
        }

        $organisation_result = $this->apply_single_rule_for_organisation_bookings($organisation_override_bookings, $active_rules, $default_rule_id);
        $customer_result = $this->apply_single_rule_for_customer_bookings($customer_override_bookings, $active_rules, $default_rule_id);
        $default_result = $this->apply_single_default_rule($default_rule_bookings, $active_rules, $default_rule_id);

        return $this->merge_process_results([$organisation_result, $customer_result, $default_result]);
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

            return $interval->invert === 0 || ($interval->invert === 1 && $interval->days <= \intval($rule['trigger_offset_days'] ?? 0));
        }
        if (($rule['trigger_timing'] ?? 'confirmation') === 'days_after_booking_date') {
            $booking_date = new DateTime($booking['StartDate']);
            $now = new DateTime();
            $interval = $booking_date->diff($now);

            return $interval->invert === 0 && $interval->days >= \intval($rule['trigger_offset_days'] ?? 0);
        }

        return false;
    }

    protected function get_bookings_for_invoicing(array $delegated_recurring_bookings = []) : array {
        $single_bookings = $this->booking_service->get_uninvoiced_single_bookings();

        return $this->deduplicate_bookings_by_id(array_merge($single_bookings, $delegated_recurring_bookings));
    }

    protected function apply_single_rule_for_organisation_bookings(array $bookings, array $active_rules, int $default_rule_id): array {
        return $this->create_invoices_for_resolved_rule_groups(
            $bookings,
            function (array $booking) use ($active_rules, $default_rule_id): array {
                return $this->resolve_organisation_rule_for_booking($booking, $active_rules, $default_rule_id);
            }
        );
    }

    protected function apply_single_rule_for_customer_bookings(array $bookings, array $active_rules, int $default_rule_id): array {
        return $this->create_invoices_for_resolved_rule_groups(
            $bookings,
            function (array $booking) use ($active_rules, $default_rule_id): array {
                return $this->resolve_customer_rule_for_booking($booking, $active_rules, $default_rule_id);
            }
        );
    }

    protected function apply_single_default_rule(array $bookings, array $active_rules, int $default_rule_id): array {
        return $this->create_invoices_for_resolved_rule_groups(
            $bookings,
            function () use ($active_rules, $default_rule_id): array {
                return $this->resolve_default_rule($active_rules, $default_rule_id);
            }
        );
    }

    /**
     * @param array<int, array> $bookings
     * @param callable $rule_resolver
     * @return array{created_invoice_ids: array<int>}
     */
    protected function create_invoices_for_resolved_rule_groups(array $bookings, callable $rule_resolver): array {
        $result = $this->empty_process_result();
        $bookings_by_rule = [];

        foreach ($bookings as $booking) {
            $rule = $rule_resolver($booking);

            if (empty($rule['enabled']) || !$this->is_trigger_condition_met($booking, $rule)) {
                continue;
            }

            $rule_bucket_key = $this->build_rule_bucket_key($rule);
            if (!isset($bookings_by_rule[$rule_bucket_key])) {
                $bookings_by_rule[$rule_bucket_key] = [
                    'rule' => $rule,
                    'bookings' => [],
                ];
            }

            $bookings_by_rule[$rule_bucket_key]['bookings'][] = $booking;
        }

        foreach ($bookings_by_rule as $group) {
            $result['created_invoice_ids'] = array_merge(
                $result['created_invoice_ids'],
                $this->create_single_invoices_for_rule($group['bookings'], $group['rule'])
            );
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $rule
     */
    protected function build_rule_bucket_key(array $rule): string {
        return implode('|', [
            (string) \intval($rule['id'] ?? 0),
            (string) ($rule['group_by'] ?? 'per_booking'),
            (string) ($rule['trigger_timing'] ?? 'confirmation'),
            (string) \intval($rule['trigger_offset_days'] ?? 0),
            (string) \intval($rule['due_date_offset_days'] ?? 30),
            !empty($rule['enabled']) ? '1' : '0',
        ]);
    }

    /**
     * Stub: single rule-specific invoice creation will be implemented later.
     *
     * @param array<int, array> $bookings
     * @param array<string, mixed> $rule
     * @return array<int>
     */
    protected function create_single_invoices_for_rule(array $bookings, array $rule): array {
        // The first thing to do, is to check that the booking hasn't already been invoiced.
        $actual_bookings = [];
        foreach ($bookings as $booking) {
            if ($this->booking_service->booking_has_invoices(intval($booking['Id'] ?? 0))) {
                // If there are any invoices for the booking, we skip it to avoid duplicates.
                continue;
            }
            $actual_bookings[] = $booking;
        }
        if (empty($actual_bookings)) {
            return [];
        }

        $booking_ids = array_map(fn($b) => \intval($b['Id'] ?? 0), $actual_bookings);
        $invoice_ids = $this->invoice_generator_service->generate_invoices_from_bookings(
            $booking_ids,
            [
                'group_by' => $rule['group_by'] ?? 'per_booking',
                'rule_scope' => 'single',
                'rule_id' => \intval($rule['id'] ?? 0),
                'due_date_offset_days' => \intval($rule['due_date_offset_days'] ?? 30),
            ]
        );

        if ($invoice_ids instanceof \WP_Error) {
            return [];
        }

        return $invoice_ids;

    }

    protected function booking_has_non_default_organisation_rule(array $booking, array $active_rules, int $default_rule_id): bool {
        $rule_id = \intval($booking['OrganisationSingleBookingAutoInvoiceRuleId'] ?? 0);

        if ($rule_id <= 0 || !isset($active_rules[$rule_id])) {
            return false;
        }

        return $default_rule_id <= 0 || $rule_id !== $default_rule_id;
    }

    protected function booking_has_non_default_customer_rule(array $booking, array $active_rules, int $default_rule_id): bool {
        $rule_id = \intval($booking['CustomerSingleBookingAutoInvoiceRuleId'] ?? 0);

        if ($rule_id <= 0 || !isset($active_rules[$rule_id])) {
            return false;
        }

        return $default_rule_id <= 0 || $rule_id !== $default_rule_id;
    }

    protected function resolve_organisation_rule_for_booking(array $booking, array $active_rules, int $default_rule_id): array {
        $rule_id = \intval($booking['OrganisationSingleBookingAutoInvoiceRuleId'] ?? 0);
        if ($rule_id > 0 && isset($active_rules[$rule_id])) {
            return $active_rules[$rule_id];
        }

        return $this->resolve_default_rule($active_rules, $default_rule_id);
    }

    protected function resolve_customer_rule_for_booking(array $booking, array $active_rules, int $default_rule_id): array {
        $rule_id = \intval($booking['CustomerSingleBookingAutoInvoiceRuleId'] ?? 0);
        if ($rule_id > 0 && isset($active_rules[$rule_id])) {
            return $active_rules[$rule_id];
        }

        return $this->resolve_default_rule($active_rules, $default_rule_id);
    }

    protected function resolve_default_rule(array $active_rules, int $default_rule_id): array {
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
            'trigger_offset_days' => max(0, \intval(myvh_setting('invoicing.single_trigger_offset_days', 0))),
            'group_by' => $this->normalize_key(myvh_setting('invoicing.single_group_by', 'per_booking')),
            'due_date_offset_days' => max(0, \intval(myvh_setting('invoicing.single_due_date_offset_days', 30))),
        ];
    }

    protected function get_active_rules_by_id(): array {
        $active_rules = [];
        foreach ($this->rule_repository->get_active_rules() as $rule) {
            $active_rules[intval($rule['Id'])] = $this->map_rule_record($rule);
        }

        return $active_rules;
    }

    protected function merge_process_results(array $results): array {
        $merged = $this->empty_process_result();

        foreach ($results as $result) {
            $merged['created_invoice_ids'] = array_merge($merged['created_invoice_ids'], $result['created_invoice_ids'] ?? []);
        }

        return [
            'created_invoice_ids' => array_values(array_unique(array_map('intval', $merged['created_invoice_ids']))),
        ];
    }

    protected function empty_process_result(): array {
        return [
            'created_invoice_ids' => [],
        ];
    }

    protected function deduplicate_bookings_by_id(array $bookings): array {
        $indexed = [];

        foreach ($bookings as $booking) {
            $booking_id = \intval($booking['Id'] ?? 0);
            if ($booking_id <= 0) {
                continue;
            }

            $indexed[$booking_id] = $booking;
        }

        return array_values($indexed);
    }

    protected function map_rule_record(array $rule): array {
        return [
            'id' => \intval($rule['Id'] ?? 0),
            'enabled' => !empty($rule['IsActive']),
            'trigger_timing' => $this->normalize_key($rule['TriggerTiming'] ?? 'confirmation'),
            'trigger_offset_days' => max(0, \intval($rule['TriggerOffsetDays'] ?? 0)),
            'group_by' => $this->normalize_key($rule['GroupBy'] ?? 'per_booking'),
            'due_date_offset_days' => max(0, \intval($rule['DueDateOffsetDays'] ?? 30)),
        ];
    }

    protected function normalize_key($value): string {
        $value = strtolower((string) $value);

        return preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '';
    }

}