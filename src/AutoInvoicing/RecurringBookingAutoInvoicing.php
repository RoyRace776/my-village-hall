<?php

namespace MYVH\AutoInvoicing;

use DateTime;
use MYVH\Bookings\BookingService;
use MYVH\Invoices\InvoiceGeneratorService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Recurring Booking Auto-Invoicing
 *
 * Handles automatic invoice generation for recurring bookings based on configured rules.
 */
class RecurringBookingAutoInvoicing {

    protected InvoiceGeneratorService $invoice_generator_service;
    protected BookingService $booking_service;
    protected RecurringBookingAutoInvoiceRuleRepository $rule_repository;
    protected LoggerInterface $logger;

    public function __construct(InvoiceGeneratorService $invoice_generator_service,
                                BookingService $booking_service,
                                RecurringBookingAutoInvoiceRuleRepository $rule_repository,
                                ?LoggerInterface $logger = null) {
        $this->invoice_generator_service = $invoice_generator_service;
        $this->booking_service = $booking_service;
        $this->rule_repository = $rule_repository;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Main method to evaluate and generate invoice for a recurring booking
     */
    public function process() : int {
        $result = $this->process_with_result();

        return \count($result['created_invoice_ids'] ?? []);
    }

    /**
     * Process recurring bookings and return a structured result for orchestration.
     *
     * Groups bookings by organisation/customer, resolves rules per group, and applies
     * period-based invoicing by filtering bookings within the calculated period range
     * and creating invoices per recurring pattern.
     *
     * @return array{created_invoice_ids: array<int>, treat_as_single_bookings: array<int, array>}
     */
    public function process_with_result(): array {
        $this->logger->info('Starting recurring booking auto-invoicing process.');
        $bookings = $this->get_confirmed_bookings_for_invoicing();
        $this->logger->info(sprintf('Found %d confirmed recurring bookings eligible for invoicing.', count($bookings)));
        $this->logger->debug(sprintf('Recurring auto-invoicing fetched %d confirmed bookings from booking service.', count($bookings)));
        if (empty($bookings)) {
            $this->logger->debug('Recurring auto-invoicing exiting early because there are no confirmed recurring bookings to process.');
            return $this->empty_process_result();
        }

        $active_rules = $this->get_active_rules_by_id();
        $default_rule_id = \intval(myvh_setting('invoicing.recurring_default_rule_id', 0));
        $this->logger->debug(sprintf(
            'Loaded %d active recurring auto-invoice rules. Default rule id from settings: %d.',
            count($active_rules),
            $default_rule_id
        ));

        $bookings_by_group = $this->group_bookings_by_organisation_customer($bookings);
        $this->logger->debug(sprintf(
            'Grouped confirmed recurring bookings into %d billing targets (%s).',
            count($bookings_by_group),
            implode(', ', array_keys($bookings_by_group))
        ));
        $result = $this->empty_process_result();

        foreach ($bookings_by_group as $group_key => $group_bookings) {
            if (empty($group_bookings)) {
                continue;
            }

            $sample_booking = reset($group_bookings);
            if (!\is_array($sample_booking)) {
                $this->logger->debug(sprintf('Skipping group %s because the sample booking could not be resolved as an array.', $group_key));
                continue;
            }

            // Resolve rule per organisation/customer group
            $rule = $this->resolve_rule_for_booking($sample_booking, $active_rules, $default_rule_id);
            $this->logger->debug(sprintf(
                'Resolved recurring rule %d for group %s (trigger_timing=%s, enabled=%s).',
                \intval($rule['id'] ?? 0),
                $group_key,
                (string) ($rule['trigger_timing'] ?? ''),
                !empty($rule['enabled']) ? 'true' : 'false'
            ));

            // Classify bookings based on rule's trigger timing
            if (($rule['trigger_timing'] ?? '') === 'treat_as_single_bookings') {
                $this->logger->info(sprintf('Group %s will be treated as single bookings.', $group_key));
                $this->logger->debug(sprintf(
                    'Group %s moved %d bookings to treat_as_single_bookings bucket.',
                    $group_key,
                    count($group_bookings)
                ));
                $result['treat_as_single_bookings'] = array_merge(
                    $result['treat_as_single_bookings'],
                    $group_bookings
                );
                continue;
            }

            if (empty($rule['enabled']) || ($rule['trigger_timing'] ?? '') === 'manual_invoicing') {
                $this->logger->info(sprintf('Group %s uses manual invoicing; skipping.', $group_key));
                $this->logger->debug(sprintf(
                    'Skipping group %s due to rule state (enabled=%s, trigger_timing=%s).',
                    $group_key,
                    !empty($rule['enabled']) ? 'true' : 'false',
                    (string) ($rule['trigger_timing'] ?? '')
                ));
                continue;
            }

            // Handle period-based invoicing
            $period = $this->calculate_invoicing_period_range($rule);
            $this->logger->info(sprintf(
                'Processing group %s for period-based invoicing with range %s to %s.',
                $group_key,
                $period['start_date'],
                $period['end_date']
            ));

            // Filter group bookings by period (no DB re-query)
            $period_bookings = $this->filter_bookings_by_period($group_bookings, $period['start_date'], $period['end_date']);
            $this->logger->debug(sprintf(
                'Group %s has %d/%d bookings in period %s to %s.',
                $group_key,
                count($period_bookings),
                count($group_bookings),
                $period['start_date'],
                $period['end_date']
            ));
            if (empty($period_bookings)) {
                $this->logger->info(sprintf('No bookings in period for group %s.', $group_key));
                continue;
            }

            // Group filtered bookings by recurring pattern and create invoices per pattern
            $patterns_to_bookings = $this->group_bookings_by_pattern($period_bookings);
            $this->logger->debug(sprintf('Group %s produced %d recurring pattern buckets for invoicing.', $group_key, count($patterns_to_bookings)));
            foreach ($patterns_to_bookings as $pattern_id => $pattern_bookings) {
                if (\intval($pattern_id) <= 0 || empty($pattern_bookings)) {
                    $this->logger->debug(sprintf('Skipping invalid/empty pattern bucket in group %s (pattern_id=%s, booking_count=%d).', $group_key, (string) $pattern_id, count($pattern_bookings)));
                    continue;
                }

                $this->logger->info(sprintf(
                    'Creating invoice for group %s, recurring pattern %d with %d bookings.',
                    $group_key,
                    $pattern_id,
                    count($pattern_bookings)
                ));

                $created_invoice_ids = $this->create_recurring_invoices_for_rule($pattern_bookings, $rule);
                $this->logger->debug(sprintf(
                    'Pattern %d in group %s created %d invoices (%s).',
                    $pattern_id,
                    $group_key,
                    count($created_invoice_ids),
                    empty($created_invoice_ids) ? 'none' : implode(', ', array_map('strval', $created_invoice_ids))
                ));
                $result['created_invoice_ids'] = array_merge(
                    $result['created_invoice_ids'],
                    $created_invoice_ids
                );
            }
        }

        $final_result = [
            'created_invoice_ids' => array_values(array_unique(array_map('intval', $result['created_invoice_ids']))),
            'treat_as_single_bookings' => $this->deduplicate_bookings_by_id($result['treat_as_single_bookings']),
        ];

        $this->logger->debug(sprintf(
            'Recurring auto-invoicing finished with %d created invoices and %d bookings marked as treat_as_single_bookings.',
            count($final_result['created_invoice_ids']),
            count($final_result['treat_as_single_bookings'])
        ));

        return $final_result;
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
        $this->logger->debug(sprintf(
            'Calculating recurring invoicing period range (rule_id=%d, timing=%s, direction=%s, period_count=%d).',
            \intval($rule['id'] ?? 0),
            $timing,
            $direction,
            $period_count
        ));

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
            $this->logger->debug(sprintf('Unable to calculate recurring period boundary for timing=%s. Falling back to current day.', $timing));
            return [
                'start_date' => $now->format('Y-m-d'),
                'end_date' => $now->format('Y-m-d'),
            ];
        }

        // Offset from current period boundary.
        // In advance is treated as an inclusive count, so 1 means current period,
        // 2 means one period back, etc.
        $effective_period_count = $direction === 'in_advance'
            ? max(0, $period_count - 1)
            : $period_count;
        $offset = $direction === 'in_arrears' ? $effective_period_count : -$effective_period_count;

        if ($offset !== 0) {
            if ($period_unit === 'quarter') {
                $period_start->modify($offset * 3 . ' months');
            } else {
                $period_start->modify($offset . ' ' . $period_unit);
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

        $period = [
            'start_date' => $period_start->format('Y-m-d'),
            'end_date' => $period_end->format('Y-m-d'),
        ];

        $this->logger->debug(sprintf(
            'Calculated recurring invoicing period range: %s to %s.',
            $period['start_date'],
            $period['end_date']
        ));

        return $period;
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

        $confirmed_bookings = array_values(array_filter($bookings, function (array $booking): bool {
            return ($booking['Status'] ?? '') === 'confirmed';
        }));

        $this->logger->debug(sprintf(
            'Booking filter for recurring auto-invoicing retained %d confirmed bookings from %d uninvoiced recurring bookings.',
            count($confirmed_bookings),
            count($bookings)
        ));

        return $confirmed_bookings;
    }

    /**
     * @param array<int, array<string, mixed>> $bookings
     * @param array<string, mixed> $rule
     * @return array<int>
     */
    protected function create_recurring_invoices_for_rule(array $bookings, array $rule): array {
        $this->logger->debug(sprintf(
            'Starting invoice generation for recurring rule %d with %d candidate bookings.',
            \intval($rule['id'] ?? 0),
            count($bookings)
        ));
        $actual_bookings = [];
        foreach ($bookings as $booking) {
            if ($this->booking_service->booking_has_invoices(intval($booking['Id'] ?? 0))) {
                $this->logger->debug(sprintf('Skipping booking %d because it already has invoices.', \intval($booking['Id'] ?? 0)));
                continue;
            }
            $actual_bookings[] = $booking;
        }

        $this->logger->debug(sprintf(
            'Recurring rule %d has %d bookings remaining after removing already-invoiced items.',
            \intval($rule['id'] ?? 0),
            count($actual_bookings)
        ));

        if (empty($actual_bookings)) {
            $this->logger->debug(sprintf('No bookings left for recurring rule %d after de-duplication checks.', \intval($rule['id'] ?? 0)));
            return [];
        }

        $invoice_ids = [];
        $bookings_by_group = $this->partition_recurring_bookings_by_billing_target($actual_bookings);

        foreach ($bookings_by_group as $group_by => $group_bookings) {
            $booking_ids = array_values(array_filter(array_map(static fn(array $booking): int => \intval($booking['Id'] ?? 0), $group_bookings)));
            if (empty($booking_ids)) {
                $this->logger->debug(sprintf('Skipping billing target %s because it has no valid booking IDs.', $group_by));
                continue;
            }

            $this->logger->debug(sprintf(
                'Generating recurring invoices using billing target %s for %d bookings (%s).',
                $group_by,
                count($booking_ids),
                implode(', ', array_map('strval', $booking_ids))
            ));

            $generated = $this->invoice_generator_service->generate_invoices_from_bookings(
                $booking_ids,
                [
                    'group_by' => $group_by,
                    'rule_scope' => 'recurring',
                    'rule_id' => \intval($rule['id'] ?? 0),
                    'due_date_offset_days' => \intval($rule['due_date_offset_days'] ?? 30),
                ]
            );

            if ($generated instanceof \WP_Error) {
                $this->logger->debug(sprintf(
                    'Invoice generation returned WP_Error for recurring rule %d on billing target %s: %s',
                    \intval($rule['id'] ?? 0),
                    $group_by,
                    $generated->get_error_message()
                ));
                continue;
            }

            $this->logger->debug(sprintf(
                'Invoice generator created %d invoices for billing target %s (%s).',
                count($generated),
                $group_by,
                empty($generated) ? 'none' : implode(', ', array_map('strval', $generated))
            ));

            $invoice_ids = array_merge($invoice_ids, $generated);
        }

        $unique_invoice_ids = array_values(array_unique(array_map('intval', $invoice_ids)));
        $this->logger->debug(sprintf(
            'Finished recurring invoice generation for rule %d with %d unique invoice IDs (%s).',
            \intval($rule['id'] ?? 0),
            count($unique_invoice_ids),
            empty($unique_invoice_ids) ? 'none' : implode(', ', array_map('strval', $unique_invoice_ids))
        ));

        return $unique_invoice_ids;
    }

    /**
     * @param array<int, array<string, mixed>> $bookings
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function partition_recurring_bookings_by_billing_target(array $bookings): array {
        $grouped = [
            'by_organisation' => [],
            'by_customer' => [],
        ];

        foreach ($bookings as $booking) {
            $organisation_id = \intval($booking['OrganisationId'] ?? 0);
            $organisation_billing_enabled = !empty($booking['OrganisationInvoiceOrganisationBookings']);

            if ($organisation_id > 0 && $organisation_billing_enabled) {
                $grouped['by_organisation'][] = $booking;
                continue;
            }

            $grouped['by_customer'][] = $booking;
        }

        return array_filter($grouped, static fn(array $items): bool => !empty($items));
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
        $skipped_without_pattern = 0;

        foreach ($bookings as $booking) {
            $pattern_id = \intval($booking['RecurringPatternId'] ?? 0);
            if ($pattern_id <= 0) {
                $skipped_without_pattern++;
                continue;
            }

            if (!isset($grouped[$pattern_id])) {
                $grouped[$pattern_id] = [];
            }

            $grouped[$pattern_id][] = $booking;
        }

        $this->logger->debug(sprintf(
            'Grouped %d bookings into %d recurring patterns; skipped %d bookings with invalid pattern id.',
            count($bookings),
            count($grouped),
            $skipped_without_pattern
        ));

        return $grouped;
    }

    /**
     * Group bookings by organisation/customer billing target.
     *
     * @param array<int, array<string, mixed>> $bookings
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function group_bookings_by_organisation_customer(array $bookings): array {
        $grouped = [
            'by_organisation' => [],
            'by_customer' => [],
        ];

        foreach ($bookings as $booking) {
            $organisation_id = \intval($booking['OrganisationId'] ?? 0);
            $organisation_billing_enabled = !empty($booking['OrganisationInvoiceOrganisationBookings']);

            if ($organisation_id > 0 && $organisation_billing_enabled) {
                $grouped['by_organisation'][] = $booking;
                continue;
            }

            $grouped['by_customer'][] = $booking;
        }

        // Return only non-empty groups
        $filtered_groups = array_filter($grouped, static fn(array $items): bool => !empty($items));
        $this->logger->debug(sprintf(
            'Booking grouping produced %d organisation bookings and %d customer bookings from %d total bookings.',
            count($grouped['by_organisation']),
            count($grouped['by_customer']),
            count($bookings)
        ));

        return $filtered_groups;
    }

    /**
     * Filter bookings to only those within the specified period range.
     *
     * Filters based on booking start date, comparing against the period start and end dates.
     *
     * @param array<int, array<string, mixed>> $bookings
     * @param string $start_date Date in Y-m-d format
     * @param string $end_date Date in Y-m-d format
     * @return array<int, array<string, mixed>>
     */
    protected function filter_bookings_by_period(array $bookings, string $start_date, string $end_date): array {
        $filtered = [];
        $skipped_without_start_date = 0;

        foreach ($bookings as $booking) {
            $booking_date = (string) ($booking['StartDate'] ?? '');
            if (empty($booking_date)) {
                $skipped_without_start_date++;
                continue;
            }

            // Compare dates as strings (Y-m-d format)
            if ($booking_date >= $start_date && $booking_date <= $end_date) {
                $filtered[] = $booking;
            }
        }

        $this->logger->debug(sprintf(
            'Period filter %s to %s kept %d/%d bookings; skipped %d without start date.',
            $start_date,
            $end_date,
            count($filtered),
            count($bookings),
            $skipped_without_start_date
        ));

        return $filtered;
    }

    protected function resolve_default_rule(array $active_rules, int $default_rule_id): array {
        if ($default_rule_id > 0 && isset($active_rules[$default_rule_id])) {
            $this->logger->debug(sprintf('Using configured default recurring rule id %d.', $default_rule_id));
            return $active_rules[$default_rule_id];
        }

        if (!empty($active_rules)) {
            $fallback_rule = reset($active_rules);
            $this->logger->debug(sprintf('Configured default recurring rule unavailable. Falling back to first active rule id %d.', \intval($fallback_rule['id'] ?? 0)));

            return $fallback_rule;
        }

        $settings_fallback_rule = [
            'id' => 0,
            'enabled' => (bool) myvh_setting('invoicing.recurring_enabled', false),
            'trigger_timing' => $this->normalize_key(myvh_setting('invoicing.recurring_trigger_timing', 'start_of_month')),
            'trigger_direction' => $this->normalize_key(myvh_setting('invoicing.recurring_trigger_direction', 'in_advance')),
            'trigger_period_count' => max(0, \intval(myvh_setting('invoicing.recurring_trigger_period_count', myvh_setting('invoicing.recurring_trigger_offset_days', 0)))),
            'trigger_offset_days' => max(0, \intval(myvh_setting('invoicing.recurring_trigger_offset_days', 0))),
            'group_by' => $this->normalize_key(myvh_setting('invoicing.recurring_group_by', 'per_booking')),
            'due_date_offset_days' => max(0, \intval(myvh_setting('invoicing.recurring_due_date_offset_days', 30))),
        ];

        $this->logger->debug(sprintf(
            'No active recurring rules found. Using settings-based fallback rule (enabled=%s, trigger_timing=%s, trigger_direction=%s).',
            !empty($settings_fallback_rule['enabled']) ? 'true' : 'false',
            (string) $settings_fallback_rule['trigger_timing'],
            (string) $settings_fallback_rule['trigger_direction']
        ));

        return $settings_fallback_rule;
    }

    protected function get_active_rules_by_id(): array {
        $active_rules = [];
        foreach ($this->rule_repository->get_active_rules() as $rule) {
            $active_rules[intval($rule['Id'])] = $this->map_rule_record($rule);
        }

        $this->logger->debug(sprintf('Mapped %d active recurring auto-invoice rules by id.', count($active_rules)));

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

    protected function build_rule_bucket_key(array $rule): string {
        $id = \intval($rule['id'] ?? 0);
        if ($id > 0) {
            return 'id:' . $id;
        }

        $timing = (string) ($rule['trigger_timing'] ?? '');
        $direction = (string) ($rule['trigger_direction'] ?? '');
        $period_count = \intval($rule['trigger_period_count'] ?? $rule['trigger_offset_days'] ?? 0);
        $due_days = \intval($rule['due_date_offset_days'] ?? 30);

        return 'fallback:' . $timing . '|' . $direction . '|' . $period_count . '|' . $due_days;
    }

    protected function normalize_key(string $value): string {
        $value = strtolower((string) $value);

        return preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '';
    }
}
