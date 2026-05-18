<?php

namespace MYVH\AutoInvoicing;

use DateTime;
use MYVH\Bookings\BookingService;
use MYVH\Invoices\InvoiceGeneratorService;

/**
 * Recurring Booking Auto-Invoicing
 *
 * Handles automatic invoice generation for recurring bookings based on configured rules.
 */
class RecurringBookingAutoInvoicing {

    protected InvoiceGeneratorService $invoice_generator_service;
    protected BookingService $booking_service;
    protected RecurringBookingAutoInvoiceRuleRepository $rule_repository;

    public function __construct(InvoiceGeneratorService $invoice_generator_service,
                                BookingService $booking_service,
                                RecurringBookingAutoInvoiceRuleRepository $rule_repository) {
        $this->invoice_generator_service = $invoice_generator_service;
        $this->booking_service = $booking_service;
        $this->rule_repository = $rule_repository;
    }

    /**
     * Main method to evaluate and generate invoice for a recurring booking
     */
    public function process() : int {
        $result = $this->process_with_result();

        return count($result['created_invoice_ids'] ?? []);
    }

    /**
     * Process recurring bookings and return a structured result for orchestration.
     *
     * Uses period-based invoicing by selecting bookings in the rule's period range
     * for each recurring pattern.
     *
     * @return array{created_invoice_ids: array<int>, treat_as_single_bookings: array<int, array>}
     */
    public function process_with_result(): array {
        $bookings = $this->get_confirmed_bookings_for_invoicing();
        if (empty($bookings)) {
            return $this->empty_process_result();
        }

        $active_rules = $this->get_active_rules_by_id();
        $default_rule_id = \intval(myvh_setting('invoicing.recurring_default_rule_id', 0));

        $patterns_to_bookings = $this->group_bookings_by_pattern($bookings);
        $result = $this->empty_process_result();

        foreach ($patterns_to_bookings as $pattern_id => $pattern_bookings) {
            if (\intval($pattern_id) <= 0 || empty($pattern_bookings)) {
                continue;
            }

            $sample_booking = reset($pattern_bookings);
            if (!is_array($sample_booking) || empty($sample_booking)) {
                continue;
            }

            $rule = $this->resolve_rule_for_booking($sample_booking, $active_rules, $default_rule_id);

            if (($rule['trigger_timing'] ?? '') === 'treat_as_single_bookings') {
                $result['treat_as_single_bookings'] = array_merge(
                    $result['treat_as_single_bookings'],
                    $pattern_bookings
                );
                continue;
            }

            if (empty($rule['enabled']) || ($rule['trigger_timing'] ?? '') === 'manual_invoicing') {
                continue;
            }

            $period = $this->calculate_invoicing_period_range($rule);
            $period_bookings = $this->booking_service->get_bookings_by_recurring_pattern_in_period(
                \intval($pattern_id),
                $period['start_date'],
                $period['end_date']
            );

            if (empty($period_bookings)) {
                continue;
            }

            $result['created_invoice_ids'] = array_merge(
                $result['created_invoice_ids'],
                $this->create_recurring_invoices_for_rule($period_bookings, $rule)
            );
        }

        return [
            'created_invoice_ids' => array_values(array_unique(array_map('intval', $result['created_invoice_ids']))),
            'treat_as_single_bookings' => $this->deduplicate_bookings_by_id($result['treat_as_single_bookings']),
        ];
    }

    /**
     * Calculate the invoicing period range based on the rule's trigger settings.
     *
     * @param array<string, mixed> $rule
     * @return array{start_date: string, end_date: string}
     */
    protected function calculate_invoicing_period_range(array $rule): array {
        $timing = (string) ($rule['trigger_timing'] ?? 'start_of_month');
        $direction = (string) ($rule['trigger_direction'] ?? 'in_advance');
        $period_count = max(0, \intval($rule['trigger_period_count'] ?? $rule['trigger_offset_days'] ?? 0));

        $now = new DateTime();
        $period_start = null;
        $period_unit = '';

        if ($timing === 'start_of_week') {
            $period_start = new DateTime($now->format('Y-m-d'));
            $period_start->modify('Monday this week');
            $period_unit = 'week';
        } elseif ($timing === 'start_of_month') {
            $period_start = new DateTime($now->format('Y-m-01'));
            $period_unit = 'month';
        } elseif ($timing === 'start_of_quarter') {
            $month = \intval($now->format('m'));
            $quarter_start_month = 1 + (intdiv($month - 1, 3) * 3);
            $period_start = new DateTime($now->format('Y') . '-' . str_pad((string) $quarter_start_month, 2, '0', STR_PAD_LEFT) . '-01');
            $period_unit = 'quarter';
        }

        if (!$period_start instanceof DateTime || $period_unit === '') {
            return [
                'start_date' => $now->format('Y-m-d'),
                'end_date' => $now->format('Y-m-d'),
            ];
        }

        if ($period_count > 0) {
            $modifier_sign = $direction === 'in_arrears' ? '+' : '-';
            if ($period_unit === 'quarter') {
                $period_start->modify($modifier_sign . ($period_count * 3) . ' months');
            } else {
                $period_start->modify($modifier_sign . $period_count . ' ' . $period_unit);
            }
        }

        $period_end = null;
        if ($period_unit === 'week') {
            $period_end = (new DateTime($period_start->format('Y-m-d')))->modify('+6 days');
        } elseif ($period_unit === 'month') {
            $period_end = (new DateTime($period_start->format('Y-m-01')))->modify('last day of this month');
        } elseif ($period_unit === 'quarter') {
            $period_end = (new DateTime($period_start->format('Y-m-d')))->modify('+3 months')->modify('-1 day');
        }

        if (!$period_end instanceof DateTime) {
            $period_end = clone $period_start;
        }

        return [
            'start_date' => $period_start->format('Y-m-d'),
            'end_date' => $period_end->format('Y-m-d'),
        ];
    }

    /**
     * Check if the trigger condition for auto-invoicing is met based on settings
     *
     * @param array<string, mixed> $booking
     * @param array<string, mixed> $rule
     * @return bool True if the trigger condition is met, false otherwise
     * @deprecated Use calculate_invoicing_period_range() for period-based invoicing.
     */
    protected function is_trigger_condition_met($booking, array $rule): bool {
        if (in_array(($rule['trigger_timing'] ?? 'start_of_month'), ['manual_invoicing', 'treat_as_single_bookings'], true)) {
            return false;
        }
        $trigger_date = $this->resolve_trigger_date_for_rule($rule);
        if ($trigger_date instanceof DateTime) {
            $now = new DateTime();

            return $now >= $trigger_date;
        }

        return false;
    }

    protected function resolve_trigger_date_for_rule(array $rule): ?DateTime {
        $timing = (string) ($rule['trigger_timing'] ?? 'start_of_month');
        $direction = (string) ($rule['trigger_direction'] ?? 'in_advance');
        $period_count = max(0, \intval($rule['trigger_period_count'] ?? $rule['trigger_offset_days'] ?? 0));

        $now = new DateTime();
        $period_start = null;
        $period_unit = '';

        if ($timing === 'start_of_week') {
            $period_start = new DateTime($now->format('Y-m-d'));
            $period_start->modify('Monday this week');
            $period_unit = 'week';
        } elseif ($timing === 'start_of_month') {
            $period_start = new DateTime($now->format('Y-m-01'));
            $period_unit = 'month';
        } elseif ($timing === 'start_of_quarter') {
            $month = \intval($now->format('m'));
            $quarter_start_month = 1 + (intdiv($month - 1, 3) * 3);
            $period_start = new DateTime($now->format('Y') . '-' . str_pad((string) $quarter_start_month, 2, '0', STR_PAD_LEFT) . '-01');
            $period_unit = 'quarter';
        }

        if (!$period_start instanceof DateTime || $period_unit === '') {
            return null;
        }

        if ($period_count <= 0) {
            return $period_start;
        }

        if ($period_unit === 'quarter') {
            $months = $period_count * 3;
            $modifier_sign = $direction === 'in_arrears' ? '+' : '-';
            return $period_start->modify($modifier_sign . $months . ' months');
        }

        $modifier_sign = $direction === 'in_arrears' ? '+' : '-';

        return $period_start->modify($modifier_sign . $period_count . ' ' . $period_unit);
    }

    protected function get_bookings_for_invoicing() : array {
        return $this->booking_service->get_uninvoiced_recurring_bookings();
    }

    protected function get_confirmed_bookings_for_invoicing(): array {
        $bookings = $this->get_bookings_for_invoicing();

        return array_values(array_filter($bookings, function (array $booking): bool {
            return ($booking['Status'] ?? '') === 'confirmed';
        }));
    }

    protected function apply_recurring_rule_for_organisation_bookings(array $bookings, array $active_rules, int $default_rule_id): array {
        return $this->empty_process_result();
    }

    protected function apply_recurring_rule_for_customer_bookings(array $bookings, array $active_rules, int $default_rule_id): array {
        return $this->empty_process_result();
    }

    protected function apply_recurring_default_rule(array $bookings, array $active_rules, int $default_rule_id): array {
        return $this->empty_process_result();
    }

    /**
     * @param array<int, array<string, mixed>> $bookings
     * @param array<string, mixed> $rule
     * @return array<int>
     */
    protected function create_recurring_invoices_for_rule(array $bookings, array $rule): array {
        $actual_bookings = [];
        foreach ($bookings as $booking) {
            if ($this->booking_service->booking_has_invoices(intval($booking['Id'] ?? 0))) {
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
                'rule_scope' => 'recurring',
                'rule_id' => \intval($rule['id'] ?? 0),
                'due_date_offset_days' => \intval($rule['due_date_offset_days'] ?? 30),
            ]
        );

        if ($invoice_ids instanceof \WP_Error) {
            return [];
        }

        return $invoice_ids;
    }

    protected function merge_process_results(array $results): array {
        $merged = $this->empty_process_result();

        foreach ($results as $result) {
            $merged['created_invoice_ids'] = array_merge($merged['created_invoice_ids'], $result['created_invoice_ids'] ?? []);
            $merged['treat_as_single_bookings'] = array_merge($merged['treat_as_single_bookings'], $result['treat_as_single_bookings'] ?? []);
        }

        return [
            'created_invoice_ids' => array_values(array_unique(array_map('intval', $merged['created_invoice_ids']))),
            'treat_as_single_bookings' => $this->deduplicate_bookings_by_id($merged['treat_as_single_bookings']),
        ];
    }

    protected function empty_process_result(): array {
        return [
            'created_invoice_ids' => [],
            'treat_as_single_bookings' => [],
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

    protected function booking_has_non_default_organisation_rule(array $booking, array $active_rules, int $default_rule_id): bool {
        $rule_id = \intval($booking['OrganisationRecurringBookingAutoInvoiceRuleId'] ?? 0);

        if ($rule_id <= 0 || !isset($active_rules[$rule_id])) {
            return false;
        }

        return $default_rule_id <= 0 || $rule_id !== $default_rule_id;
    }

    protected function booking_has_non_default_customer_rule(array $booking, array $active_rules, int $default_rule_id): bool {
        $rule_id = \intval($booking['CustomerRecurringBookingAutoInvoiceRuleId'] ?? 0);

        if ($rule_id <= 0 || !isset($active_rules[$rule_id])) {
            return false;
        }

        return $default_rule_id <= 0 || $rule_id !== $default_rule_id;
    }

    protected function resolve_organisation_rule_for_booking(array $booking, array $active_rules, int $default_rule_id): array {
        $rule_id = \intval($booking['OrganisationRecurringBookingAutoInvoiceRuleId'] ?? 0);
        if ($rule_id > 0 && isset($active_rules[$rule_id])) {
            return $active_rules[$rule_id];
        }

        return $this->resolve_default_rule($active_rules, $default_rule_id);
    }

    protected function resolve_customer_rule_for_booking(array $booking, array $active_rules, int $default_rule_id): array {
        $rule_id = \intval($booking['CustomerRecurringBookingAutoInvoiceRuleId'] ?? 0);
        if ($rule_id > 0 && isset($active_rules[$rule_id])) {
            return $active_rules[$rule_id];
        }

        return $this->resolve_default_rule($active_rules, $default_rule_id);
    }

    protected function resolve_rule_for_booking(array $booking, array $active_rules, int $default_rule_id): array {
        $rule = $this->resolve_organisation_rule_for_booking($booking, $active_rules, $default_rule_id);
        $rule_id = \intval($rule['id'] ?? 0);
        if ($rule_id > 0 && ($default_rule_id <= 0 || $rule_id !== $default_rule_id)) {
            return $rule;
        }

        $rule = $this->resolve_customer_rule_for_booking($booking, $active_rules, $default_rule_id);
        $rule_id = \intval($rule['id'] ?? 0);
        if ($rule_id > 0 && ($default_rule_id <= 0 || $rule_id !== $default_rule_id)) {
            return $rule;
        }

        return $this->resolve_default_rule($active_rules, $default_rule_id);
    }

    /**
     * @param array<int, array<string, mixed>> $bookings
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function group_bookings_by_pattern(array $bookings): array {
        $grouped = [];

        foreach ($bookings as $booking) {
            $pattern_id = \intval($booking['RecurringPatternId'] ?? 0);
            if ($pattern_id <= 0) {
                continue;
            }

            if (!isset($grouped[$pattern_id])) {
                $grouped[$pattern_id] = [];
            }

            $grouped[$pattern_id][] = $booking;
        }

        return $grouped;
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
            'enabled' => (bool) myvh_setting('invoicing.recurring_enabled', false),
            'trigger_timing' => $this->normalize_key(myvh_setting('invoicing.recurring_trigger_timing', 'start_of_month')),
            'trigger_direction' => $this->normalize_key(myvh_setting('invoicing.recurring_trigger_direction', 'in_advance')),
            'trigger_period_count' => max(0, \intval(myvh_setting('invoicing.recurring_trigger_period_count', myvh_setting('invoicing.recurring_trigger_offset_days', 0)))),
            'trigger_offset_days' => max(0, \intval(myvh_setting('invoicing.recurring_trigger_offset_days', 0))),
            'group_by' => $this->normalize_key(myvh_setting('invoicing.recurring_group_by', 'per_booking')),
            'due_date_offset_days' => max(0, \intval(myvh_setting('invoicing.recurring_due_date_offset_days', 30))),
        ];
    }

    protected function get_active_rules_by_id(): array {
        $active_rules = [];
        foreach ($this->rule_repository->get_active_rules() as $rule) {
            $active_rules[intval($rule['Id'])] = $this->map_rule_record($rule);
        }

        return $active_rules;
    }

    protected function map_rule_record(array $rule): array {
        return [
            'id' => \intval($rule['Id'] ?? 0),
            'enabled' => !empty($rule['IsActive']),
            'trigger_timing' => $this->normalize_key($rule['TriggerTiming'] ?? 'start_of_month'),
            'trigger_direction' => $this->normalize_key($rule['TriggerDirection'] ?? 'in_advance'),
            'trigger_period_count' => max(0, \intval($rule['TriggerOffsetDays'] ?? 0)),
            'trigger_offset_days' => max(0, \intval($rule['TriggerOffsetDays'] ?? 0)),
            'group_by' => $this->normalize_key($rule['GroupBy'] ?? 'per_booking'),
            'due_date_offset_days' => max(0, \intval($rule['DueDateOffsetDays'] ?? 30)),
        ];
    }

    protected function normalize_key(string $value): string {
        $value = strtolower((string) $value);

        return preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '';
    }
}
