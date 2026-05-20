<?php

namespace MYVH\Bookings;

use WP_Error;
use DateTime;
use MYVH\Bookings\RecurringPatternRepository;
use MYVH\Bookings\BookingRepository;
use MYVH\Bookings\Services\BookingChargeService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if (!defined('ABSPATH')) exit;

class RecurringPatternService {

    private $repo;
    private $booking_repo;
    private ?BookingChargeService $booking_charge_service;
    private $last_booking_results = null;
    private LoggerInterface $logger;

    public function __construct(RecurringPatternRepository $repo,
                                BookingRepository $booking_repo,
                                ?BookingChargeService $booking_charge_service = null,
                                ?LoggerInterface $logger = null) {
        $this->repo = $repo;
        $this->booking_repo = $booking_repo;
        $this->booking_charge_service = $booking_charge_service;
        $this->logger = $logger ?? new NullLogger();
    }

    public function get_all($args = []): array {
        return $this->repo->get_all($args);
    }

    public function get_by_id($id): ?array {
        return $this->repo->get_by_id($id);
    }

    public function get($id): ?array {
        return $this->repo->get_by_id($id);
    }

    public function get_active_with_bookings(): array {
        return $this->repo->get_all_active_with_bookings();
    }

    public function get_by_parent_booking($booking_id): ?array {
        return $this->repo->get_by_parent_booking($booking_id);
    }

    public function update_pattern(int $pattern_id, array $record): bool {
        return $this->repo->update($record, ['Id' => $pattern_id]);
    }

    public function get_bookings_for_pattern($pattern_id): array {
        return $this->get_booking_repo()->get_by_pattern_id($pattern_id);
    }

    public function split_pattern_from_booking(int $pattern_id, int $booking_id): array|WP_Error {
        $pattern = $this->repo->get_by_id($pattern_id);
        if (!$pattern) {
            return new WP_Error('not_found', __('Recurring pattern not found', 'my-village-hall'));
        }

        $bookings = $this->get_bookings_for_pattern($pattern_id);
        if (empty($bookings)) {
            return new WP_Error('not_found', __('Recurring bookings not found', 'my-village-hall'));
        }

        usort($bookings, static function (array $left, array $right): int {
            $left_key = sprintf(
                '%s %s %010d',
                (string) ($left['StartDate'] ?? ''),
                (string) ($left['StartTime'] ?? ''),
                \intval($left['Id'] ?? 0)
            );
            $right_key = sprintf(
                '%s %s %010d',
                (string) ($right['StartDate'] ?? ''),
                (string) ($right['StartTime'] ?? ''),
                \intval($right['Id'] ?? 0)
            );

            return strcmp($left_key, $right_key);
        });

        $selected_index = -1;
        foreach ($bookings as $index => $booking) {
            if (intval($booking['Id'] ?? 0) === $booking_id) {
                $selected_index = $index;
                break;
            }
        }

        if ($selected_index < 0) {
            return new WP_Error('not_found', __('Booking not found in recurring pattern', 'my-village-hall'));
        }

        if ($selected_index === 0) {
            return [
                'new_pattern_id' => $pattern_id,
                'future_bookings' => $bookings,
                'previous_bookings' => [],
            ];
        }

        $previous_bookings = array_slice($bookings, 0, $selected_index);
        $future_bookings = array_slice($bookings, $selected_index);
        $parent_booking = $future_bookings[0] ?? null;

        if (!$parent_booking) {
            return new WP_Error('validation', __('No future recurring bookings were found to split', 'my-village-hall'));
        }

        $new_pattern_record = [
            'ParentBookingId' => \intval($parent_booking['Id']),
            'RecurrenceType' => sanitize_text_field($pattern['RecurrenceType'] ?? ''),
            'RecurrenceInterval' => \intval($pattern['RecurrenceInterval'] ?? 1),
            'RecurrenceDay' => sanitize_text_field($pattern['RecurrenceDay'] ?? ''),
            'RecurrenceWeek' => sanitize_text_field($pattern['RecurrenceWeek'] ?? ''),
            'StartDate' => sanitize_text_field($parent_booking['StartDate'] ?? ''),
            'EndDate' => !empty($pattern['EndDate']) ? sanitize_text_field($pattern['EndDate']) : null,
            'MaxOccurrences' => !empty($pattern['MaxOccurrences']) ? count($future_bookings) : null,
            'OccurrenceCount' => count($future_bookings),
            'IsActive' => !empty($pattern['IsActive']) ? 1 : 0,
        ];

        $new_pattern_id = $this->repo->create($new_pattern_record);
        if ($new_pattern_id === false) {
            return new WP_Error('database', __('Failed to create the future recurring pattern', 'my-village-hall'));
        }

        foreach ($future_bookings as &$future_booking) {
            $updated = $this->booking_repo->update(
                ['RecurringPatternId' => $new_pattern_id],
                ['Id' => \intval($future_booking['Id'] ?? 0)]
            );

            if ($updated === false) {
                return new WP_Error('database', __('Failed to reassign recurring bookings to the future pattern', 'my-village-hall'));
            }

            $future_booking['RecurringPatternId'] = $new_pattern_id;
        }
        unset($future_booking);

        $last_previous_booking = end($previous_bookings);
        $old_pattern_update = [
            'OccurrenceCount' => count($previous_bookings),
        ];

        if ($last_previous_booking) {
            $old_pattern_update['EndDate'] = sanitize_text_field($last_previous_booking['StartDate'] ?? '');
        }

        if (!empty($pattern['MaxOccurrences'])) {
            $old_pattern_update['MaxOccurrences'] = count($previous_bookings);
        }

        $updated_pattern = $this->repo->update($old_pattern_update, ['Id' => $pattern_id]);
        if ($updated_pattern === false) {
            return new WP_Error('database', __('Failed to update the original recurring pattern', 'my-village-hall'));
        }

        if (count($previous_bookings) === 1) {
            $remaining_previous_booking_id = \intval($previous_bookings[0]['Id'] ?? 0);
            if ($remaining_previous_booking_id > 0) {
                $detached_previous = $this->booking_repo->update(
                    ['RecurringPatternId' => null],
                    ['Id' => $remaining_previous_booking_id]
                );

                if ($detached_previous === false) {
                    return new WP_Error('database', __('Failed to detach single previous booking from recurring pattern', 'my-village-hall'));
                }

                $previous_bookings[0]['RecurringPatternId'] = null;
            }

            $deactivated_old_pattern = $this->repo->update(
                ['OccurrenceCount' => 0, 'IsActive' => 0],
                ['Id' => $pattern_id]
            );

            if ($deactivated_old_pattern === false) {
                return new WP_Error('database', __('Failed to deactivate previous recurring pattern after split', 'my-village-hall'));
            }
        }

        if (count($future_bookings) === 1) {
            $remaining_future_booking_id = \intval($future_bookings[0]['Id'] ?? 0);
            if ($remaining_future_booking_id > 0) {
                $detached_future = $this->booking_repo->update(
                    ['RecurringPatternId' => null],
                    ['Id' => $remaining_future_booking_id]
                );

                if ($detached_future === false) {
                    return new WP_Error('database', __('Failed to detach single future booking from recurring pattern', 'my-village-hall'));
                }

                $future_bookings[0]['RecurringPatternId'] = null;
            }

            $deactivated_new_pattern = $this->repo->update(
                ['OccurrenceCount' => 0, 'IsActive' => 0],
                ['Id' => $new_pattern_id]
            );

            if ($deactivated_new_pattern === false) {
                return new WP_Error('database', __('Failed to deactivate future recurring pattern after split', 'my-village-hall'));
            }

            $new_pattern_id = 0;
        }

        return [
            'new_pattern_id' => $new_pattern_id,
            'future_bookings' => $future_bookings,
            'previous_bookings' => $previous_bookings,
        ];
    }

    /**
     * Save a recurring pattern and immediately create all future bookings
     */
    public function save($data): int|WP_Error {
        $this->last_booking_results = null;

        if (empty($data['parent_booking_id'])) {
            return new WP_Error('validation', __('Parent booking is required', 'my-village-hall'));
        }

        if (empty($data['recurrence_type'])) {
            return new WP_Error('validation', __('Recurrence type is required', 'my-village-hall'));
        }

        if (empty($data['start_date'])) {
            return new WP_Error('validation', __('Start date is required', 'my-village-hall'));
        }

        $valid_types = ['daily', 'weekly', 'monthly', 'monthly_day', 'yearly'];
        if (!in_array($data['recurrence_type'], $valid_types)) {
            return new WP_Error('validation', __('Invalid recurrence type', 'my-village-hall'));
        }

        // monthly_day requires a week-of-month and day-of-week
        if ($data['recurrence_type'] === 'monthly_day') {
            if (empty($data['recurrence_week']) || empty($data['recurrence_day'])) {
                return new WP_Error('validation', __('Week of month and day of week are required for this recurrence type', 'my-village-hall'));
            }
            $valid_weeks = ['1','2','3','4','last'];
            $valid_days  = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
            if (!in_array($data['recurrence_week'], $valid_weeks) || !in_array(strtolower($data['recurrence_day']), $valid_days)) {
                return new WP_Error('validation', __('Invalid week or day value', 'my-village-hall'));
            }
        }

        // Validate that we have either an end date or max occurrences
        if (empty($data['end_date']) && empty($data['max_occurrences'])) {
            return new WP_Error('validation', __('Either end date or max occurrences is required', 'my-village-hall'));
        }

        // TODO: get the 365 number from setting to limit max occurrences to prevent abuse
        if (!empty($data['max_occurrences']) && \intval($data['max_occurrences']) > 365) {
            return new WP_Error('validation', __('Maximum 365 occurrences allowed', 'my-village-hall'));
        }

        $record = [
            'ParentBookingId'       => \intval($data['parent_booking_id']),
            'RecurrenceType'        => sanitize_text_field($data['recurrence_type']),
            'RecurrenceInterval'    => \intval($data['recurrence_interval'] ?? 1),
            'RecurrenceDay'         => sanitize_text_field($data['recurrence_day'] ?? ''),
            'RecurrenceWeek'        => sanitize_text_field($data['recurrence_week'] ?? ''),
            'StartDate'             => sanitize_text_field($data['start_date']),
            'EndDate'               => !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : null,
            'MaxOccurrences'        => !empty($data['max_occurrences']) ? \intval($data['max_occurrences']) : null,
            'IsActive'              => !empty($data['is_active']) ? 1 : 0,
        ];

        // Create or update the pattern
        if (!empty($data['pattern_id'])) {
            $result = $this->repo->update($record, ['Id' => \intval($data['pattern_id'])]);
            if ($result === false) {
                return new WP_Error('database', __('Failed to update pattern', 'my-village-hall'));
            }
            $pattern_id = \intval($data['pattern_id']);
            // Delete future bookings so they can be regenerated with the new schedule
            $this->get_booking_repo()->delete_future_by_pattern($pattern_id);
        } else { //This is a new pattern
            $pattern_id = $this->repo->create($record);
            if ($pattern_id === false) {
                return new WP_Error('database', __('Failed to create pattern', 'my-village-hall'));
            }

            //Update the parent booking with the pattern id
            $update = [ 'RecurringPatternId' => $pattern_id ];
            $id = ['Id' => \intval($data['parent_booking_id'])];
            $this->booking_repo->update($update, $id);
        }

        // Immediately create all future bookings
        $booking_results = $this->create_recurring_bookings($pattern_id, $record);

        if (is_wp_error($booking_results)) {
            return $booking_results;
        }

        $this->last_booking_results = $booking_results;

        return $pattern_id;
    }

    public function get_last_booking_results(): ?array {
        return $this->last_booking_results;
    }

    /**
     * Create all bookings for a recurring pattern
     *
     * @param int $pattern_id The pattern ID
     * @param array $pattern Pattern data
     * @return array|WP_Error Results or error
     */
    public function create_recurring_bookings( mixed $pattern_id, mixed $pattern = null): array|WP_Error {
        $booking_repo = $this->get_booking_repo();

        if (!$pattern) {
            $pattern = $this->repo->get_by_id($pattern_id);
        }

        if (!$pattern) {
            return new WP_Error('not_found', __('Pattern not found', 'my-village-hall'));
        }

        // Get parent booking details
        $parent_booking = $booking_repo->get_by_id($pattern['ParentBookingId']);

        if (!$parent_booking) {
            return new WP_Error('not_found', __('Parent booking not found', 'my-village-hall'));
        }

        // Calculate all occurrence dates
        $dates = $this->calculate_all_occurrence_dates($pattern);

        if (empty($dates)) {
            return new WP_Error('validation', __('No valid occurrence dates found', 'my-village-hall'));
        }

        $results = [
            'created' => 0,
            'skipped' => 0,
            'conflicts' => [],
            'errors' => []
        ];

        $duration_days = max(0, (int) ((strtotime($parent_booking['EndDate']) - strtotime($parent_booking['StartDate'])) / 86400));

        // Create bookings for each date
        foreach ($dates as $date) {
            $child_end_date = date('Y-m-d', strtotime($date . ' +' . $duration_days . ' day'));

            // Don't recreate the parent booking
            if ($parent_booking['StartDate'] == $date && $parent_booking['EndDate'] == $child_end_date) {
                $results['created']++;
                continue;
            }
            // Check for conflicts
            $conflict = $this->check_booking_conflict(
                $parent_booking['RoomId'],
                $date,
                $parent_booking['StartTime'],
                $parent_booking['EndTime'],
                $parent_booking['Id'],
                $child_end_date
            );

            if ($conflict) {
                $results['skipped']++;
                $results['conflicts'][] = $duration_days > 0
                    ? ($date . ' to ' . $child_end_date)
                    : $date;
                continue;
            }

            // Create the booking
            $booking_data = [
                'CustomerId'         => $parent_booking['CustomerId'],
                'RoomId'             => $parent_booking['RoomId'],
                'Status'             => $parent_booking['Status'],
                'StartDate'          => $date,
                'EndDate'            => $child_end_date,
                'StartTime'          => $parent_booking['StartTime'],
                'EndTime'            => $parent_booking['EndTime'],
                'Public'             => $parent_booking['Public'],
                'Description'        => $parent_booking['Description'],
                'RecurringPatternId' => $pattern_id,
                'OrganisationId'     => $parent_booking['OrganisationId']
            ];

            $new_booking_id = $booking_repo->create($booking_data);

            if (!$new_booking_id) {
                $results['errors'][] = sprintf(
                    __('Failed to create booking for %s', 'my-village-hall'),
                    $duration_days > 0 ? ($date . ' to ' . $child_end_date) : $date
                );
                continue;
            }

            $charge_result = $this->recalculate_booking_charges((int) $new_booking_id);
            if (is_wp_error($charge_result)) {
                $booking_repo->delete((int) $new_booking_id);
                $results['skipped']++;
                $results['errors'][] = sprintf(
                    __('Failed to create booking charges for %s', 'my-village-hall'),
                    $duration_days > 0 ? ($date . ' to ' . $child_end_date) : $date
                );
                continue;
            }

            $results['created']++;
        }

        // Update occurrence count
        $this->repo->update(
            ['OccurrenceCount' => $results['created']],
            ['Id' => $pattern_id]
        );

        return $results;
    }

    /**
     * Calculate all occurrence dates for a pattern.
     *
     * Supports:
     *   daily        – every N days
     *   weekly       – every N weeks (same weekday)
     *   monthly      – every N months (same calendar date)
     *   monthly_day  – Nth weekday of the month (e.g. "first Thursday")
     *   yearly       – every N years (same date)
     *
     * @param array $pattern The pattern data row
     * @return array Array of Y-m-d strings
     */
    private function calculate_all_occurrence_dates($pattern): array {
        $dates    = [];
        $interval = max(1, \intval($pattern['RecurrenceInterval']));
        $type     = $pattern['RecurrenceType'];

        $max_occurrences = !empty($pattern['MaxOccurrences']) ? \intval($pattern['MaxOccurrences']) : 365;
        $end_date        = !empty($pattern['EndDate']) ? new DateTime($pattern['EndDate']) : null;
        $end_date?->setTime(23, 59, 59);

        if ($type === 'monthly_day') {
            return $this->calculate_monthly_day_dates($pattern, $max_occurrences, $end_date, $interval);
        }

        $current = new DateTime($pattern['StartDate']);

        while (count($dates) < $max_occurrences) {
            if ($end_date && $current > $end_date) break;

            $dates[] = $current->format('Y-m-d');

            switch ($type) {
                case 'daily':   $current->modify("+{$interval} days");   break;
                case 'weekly':  $current->modify("+{$interval} weeks");  break;
                case 'monthly': $current->modify("+{$interval} months"); break;
                case 'yearly':  $current->modify("+{$interval} years");  break;
                default: break; // unknown type – stop
            }
        }

        return $dates;
    }

    /**
     * Calculate dates for "Nth weekday of the month" patterns.
     *
     * RecurrenceWeek: '1','2','3','4','last'
     * RecurrenceDay:  'monday'…'sunday'
     * RecurrenceInterval: every N months
     *
     * @param array         $pattern
     * @param int           $max_occurrences
     * @param DateTime|null $end_date
     * @param int           $interval        months between occurrences
     * @return array
     */
    private function calculate_monthly_day_dates( mixed $pattern, mixed $max_occurrences, mixed $end_date, mixed $interval): array {
        $dates    = [];
        $week_num = $pattern['RecurrenceWeek'];   // '1','2','3','4','last'
        $day_name = strtolower($pattern['RecurrenceDay']); // 'thursday' etc.

        // Start from the first month that contains the start date
        $start        = new DateTime($pattern['StartDate']);
        $current_year = (int) $start->format('Y');
        $current_mon  = (int) $start->format('n');

        $ordinals = ['1' => 'first', '2' => 'second', '3' => 'third', '4' => 'fourth', 'last' => 'last'];
        $ordinal  = $ordinals[$week_num] ?? 'first';

        while (count($dates) < $max_occurrences) {
            // Build a DateTime for the first day of this month and find the occurrence
            $month_str = sprintf('%04d-%02d-01', $current_year, $current_mon);

            if ($week_num === 'last') {
                $date = new DateTime("last {$day_name} of {$month_str}");
            } else {
                $date = new DateTime("{$ordinal} {$day_name} of {$month_str}");
            }

            // PHP's relative date strings can land in the wrong month for 'last' — verify
            if ((int)$date->format('n') !== $current_mon || (int)$date->format('Y') !== $current_year) {
                // This month doesn't have that occurrence (e.g. 5th Thursday) – skip
            } elseif ($date >= $start) {
                if ($end_date && $date > $end_date) break;
                $dates[] = $date->format('Y-m-d');
            }
            // advance by interval months
            $current_mon += $interval;
            while ($current_mon > 12) {
                $current_mon -= 12;
                $current_year++;
            }
            // Safety: don't go more than 50 years out
            if ($current_year > (int)(new DateTime())->format('Y') + 50) break;
        }

        return $dates;
    }

    /**
     * Return a human-readable description of a pattern's schedule.
     * Used in views to avoid repeating logic.
     *
     * @param array $pattern
     * @return string
     */
    public static function describe($pattern): string {
        $interval = \intval($pattern['RecurrenceInterval'] ?? 1);
        $type     = $pattern['RecurrenceType'] ?? '';

        $ordinals  = ['1'=>'1st','2'=>'2nd','3'=>'3rd','4'=>'4th','last'=>'last'];
        $day_names = ['monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday',
                      'thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday'];

        switch ($type) {
            case 'daily':
                return $interval === 1 ? 'Daily' : "Every {$interval} days";
            case 'weekly':
                return $interval === 1 ? 'Weekly' : "Every {$interval} weeks";
            case 'monthly':
                return $interval === 1 ? 'Monthly' : "Every {$interval} months";
            case 'monthly_day':
                $ord = $ordinals[$pattern['RecurrenceWeek'] ?? '1'] ?? '?';
                $day = $day_names[strtolower($pattern['RecurrenceDay'] ?? '')] ?? '?';
                $base = "{$ord} {$day} of the month";
                return $interval > 1 ? "{$base} (every {$interval} months)" : $base;
            case 'yearly':
                return $interval === 1 ? 'Yearly' : "Every {$interval} years";
            default:
                return ucfirst($type);
        }
    }

    /**
     * Check if a booking conflicts with existing bookings.
     * Delegated to the booking repository.
     */
    private function check_booking_conflict( mixed $room_id, mixed $date, mixed $start_time, mixed $end_time, mixed $exclude_booking_id = null, mixed $end_date = null): bool {
        return $this->get_booking_repo()->has_conflict($room_id, $date, $start_time, $end_time, $exclude_booking_id, $end_date);
    }

    public function delete($id): int|false {
        return $this->repo->delete($id);
    }

    public function deactivate($id): int|false {
        return $this->repo->deactivate($id);
    }

    /**
     * Cancel all future bookings for a pattern (keeps history, marks cancelled)
     */
    public function cancel_future_bookings($pattern_id): int|false {
        return $this->get_booking_repo()->cancel_future_by_pattern($pattern_id);
    }

    /**
     * Delete all future bookings for a pattern
     */
    public function delete_future_bookings($pattern_id): int|false {
        return $this->get_booking_repo()->delete_future_by_pattern($pattern_id);
    }

    /**
     * Get or resolve the booking repository
     */
    private function get_booking_repo(): BookingRepository {
        return $this->booking_repo;
    }

    private function recalculate_booking_charges(int $booking_id): bool|WP_Error {
        if ($this->booking_charge_service === null) {
            return true;
        }

        return $this->booking_charge_service->recalculate($booking_id);
    }
}
