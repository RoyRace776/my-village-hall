<?php
if (!defined('ABSPATH')) exit;

/**
 * Recurring Pattern Service
 *
 * This version creates all bookings immediately when the pattern is saved,
 * rather than relying on cron jobs. This is more reliable and gives users
 * immediate feedback.
 */
class Recurring_Pattern_Service {

    private $repo;
    private $booking_repo;
    private $last_booking_results = null;

    public function __construct(Recurring_Pattern_Repository $repo,
                                Booking_Repository $booking_repo) {
        $this->repo = $repo;
        $this->booking_repo = $booking_repo;
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

    public function get_bookings_for_pattern($pattern_id): array {
        return $this->get_booking_repo()->get_by_pattern_id($pattern_id);
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
        if (!empty($data['max_occurrences']) && intval($data['max_occurrences']) > 365) {
            return new WP_Error('validation', __('Maximum 365 occurrences allowed', 'my-village-hall'));
        }

        $record = [
            'ParentBookingId'       => intval($data['parent_booking_id']),
            'RecurrenceType'        => sanitize_text_field($data['recurrence_type']),
            'RecurrenceInterval'    => intval($data['recurrence_interval'] ?? 1),
            'RecurrenceDay'         => sanitize_text_field($data['recurrence_day'] ?? ''),
            'RecurrenceWeek'        => sanitize_text_field($data['recurrence_week'] ?? ''),
            'StartDate'             => sanitize_text_field($data['start_date']),
            'EndDate'               => !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : null,
            'MaxOccurrences'        => !empty($data['max_occurrences']) ? intval($data['max_occurrences']) : null,
            'IsActive'              => !empty($data['is_active']) ? 1 : 0,
        ];

        // Create or update the pattern
        if (!empty($data['pattern_id'])) {
            $result = $this->repo->update($record, ['Id' => intval($data['pattern_id'])]);
            if ($result === false) {
                return new WP_Error('database', __('Failed to update pattern', 'my-village-hall'));
            }
            $pattern_id = intval($data['pattern_id']);
            // Delete future bookings so they can be regenerated with the new schedule
            $this->get_booking_repo()->delete_future_by_pattern($pattern_id);
        } else { //This is a new pattern
            $pattern_id = $this->repo->create($record);
            if ($pattern_id === false) {
                return new WP_Error('database', __('Failed to create pattern', 'my-village-hall'));
            }

            //Update the parent booking with the pattern id
            $update = [ 'RecurringPatternId' => $pattern_id ];
            $id = ['Id' => intval($data['parent_booking_id'])];
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
    public function create_recurring_bookings($pattern_id, $pattern = null): array|WP_Error {
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

        $duration_days = max(0, (int) ((strtotime($parent_booking['EndDate']) - strtotime($parent_booking['StartDate'])) / DAY_IN_SECONDS));

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

            if ($new_booking_id) {
                $results['created']++;
            } else {
                $results['errors'][] = sprintf(
                    __('Failed to create booking for %s', 'my-village-hall'),
                    $duration_days > 0 ? ($date . ' to ' . $child_end_date) : $date
                );
            }
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
        $interval = max(1, intval($pattern['RecurrenceInterval']));
        $type     = $pattern['RecurrenceType'];

        $max_occurrences = !empty($pattern['MaxOccurrences']) ? intval($pattern['MaxOccurrences']) : 365;
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
    private function calculate_monthly_day_dates($pattern, $max_occurrences, $end_date, $interval): array {
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
        $interval = intval($pattern['RecurrenceInterval'] ?? 1);
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
    private function check_booking_conflict($room_id, $date, $start_time, $end_time, $exclude_booking_id = null, $end_date = null): bool {
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
    private function get_booking_repo(): Booking_Repository {
        return $this->booking_repo;
    }
}