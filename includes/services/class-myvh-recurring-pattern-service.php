<?php
if (!defined('ABSPATH')) exit;

/**
 * Recurring Pattern Service
 * 
 * This version creates all bookings immediately when the pattern is saved,
 * rather than relying on cron jobs. This is more reliable and gives users
 * immediate feedback.
 */
class MYVH_Recurring_Pattern_Service {

    private $repo;

    public function __construct($repo) {
        $this->repo = $repo;
    }

    public function get_all($args = []) {
        return $this->repo->get_all($args);
    }

    public function get($id) {
        return $this->repo->get_by_id($id);
    }

    public function get_active_with_bookings() {
        return $this->repo->get_all_active_with_bookings();
    }

    public function get_by_parent_booking($booking_id) {
        return $this->repo->get_by_parent_booking($booking_id);
    }

    /**
     * Save a recurring pattern and immediately create all future bookings
     */
    public function save($data) {

        if (empty($data['parent_booking_id'])) {
            return new WP_Error('validation', __('Parent booking is required', 'my-village-hall'));
        }

        if (empty($data['recurrence_type'])) {
            return new WP_Error('validation', __('Recurrence type is required', 'my-village-hall'));
        }

        if (empty($data['start_date'])) {
            return new WP_Error('validation', __('Start date is required', 'my-village-hall'));
        }

        $valid_types = ['daily', 'weekly', 'monthly', 'yearly'];
        if (!in_array($data['recurrence_type'], $valid_types)) {
            return new WP_Error('validation', __('Invalid recurrence type', 'my-village-hall'));
        }

        // Validate that we have either an end date or max occurrences
        if (empty($data['end_date']) && empty($data['max_occurrences'])) {
            return new WP_Error('validation', __('Either end date or max occurrences is required', 'my-village-hall'));
        }

        // Limit max occurrences to prevent abuse
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
            'IsActive'              => isset($data['is_active']) ? 1 : 0,
        ];

        // Create or update the pattern
        if (!empty($data['pattern_id'])) {
            $result = $this->repo->update($record, ['Id' => intval($data['pattern_id'])]);
            if ($result === false) {
                return new WP_Error('database', __('Failed to update pattern', 'my-village-hall'));
            }
            $pattern_id = intval($data['pattern_id']);
        } else {
            $pattern_id = $this->repo->create($record);
            if ($pattern_id === false) {
                return new WP_Error('database', __('Failed to create pattern', 'my-village-hall'));
            }
        }

        // Immediately create all future bookings
        $booking_results = $this->create_recurring_bookings($pattern_id, $record);

        if (is_wp_error($booking_results)) {
            return $booking_results;
        }

        return $pattern_id;
    }

    /**
     * Create all bookings for a recurring pattern
     * 
     * @param int $pattern_id The pattern ID
     * @param array $pattern Pattern data
     * @return array|WP_Error Results or error
     */
    public function create_recurring_bookings($pattern_id, $pattern = null) {
        global $myvh_booking_repo;

        if (!$pattern) {
            $pattern = $this->repo->get_by_id($pattern_id);
        }

        if (!$pattern) {
            return new WP_Error('not_found', __('Pattern not found', 'my-village-hall'));
        }

        // Get parent booking details
        $parent_booking = $myvh_booking_repo->get_by_id($pattern['ParentBookingId']);
        
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

        // Create bookings for each date
        foreach ($dates as $date) {
            // Check for conflicts
            $conflict = $this->check_booking_conflict(
                $parent_booking['RoomId'],
                $date,
                $parent_booking['StartTime'],
                $parent_booking['EndTime'],
                $parent_booking['Id']
            );

            if ($conflict) {
                $results['skipped']++;
                $results['conflicts'][] = $date;
                continue;
            }

            // Create the booking
            $booking_data = [
                'CustomerId'         => $parent_booking['CustomerId'],
                'RoomId'             => $parent_booking['RoomId'],
                'Status'             => 'confirmed',
                'StartDate'          => $date,
                'EndDate'            => $date,
                'StartTime'          => $parent_booking['StartTime'],
                'EndTime'            => $parent_booking['EndTime'],
                'Public'             => $parent_booking['Public'],
                'Description'        => $parent_booking['Description'],
                'RecurringPatternId' => $pattern_id
            ];

            $new_booking_id = $myvh_booking_repo->create($booking_data);

            if ($new_booking_id) {
                $results['created']++;
            } else {
                $results['errors'][] = sprintf(
                    __('Failed to create booking for %s', 'my-village-hall'),
                    $date
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
     * Calculate all occurrence dates for a pattern
     * 
     * @param array $pattern The pattern data
     * @return array Array of date strings
     */
    private function calculate_all_occurrence_dates($pattern) {
        $dates = [];
        $current_date = new DateTime($pattern['StartDate']);
        $interval = intval($pattern['RecurrenceInterval']);
        $count = 0;

        // Set limits
        $max_occurrences = $pattern['MaxOccurrences'] ? intval($pattern['MaxOccurrences']) : 365;
        $end_date = $pattern['EndDate'] ? new DateTime($pattern['EndDate']) : null;

        while ($count < $max_occurrences) {
            // Check if we've passed the end date
            if ($end_date && $current_date > $end_date) {
                break;
            }

            // Add this date
            $dates[] = $current_date->format('Y-m-d');
            $count++;

            // Calculate next date based on recurrence type
            switch ($pattern['RecurrenceType']) {
                case 'daily':
                    $current_date->modify("+{$interval} days");
                    break;
                
                case 'weekly':
                    $current_date->modify("+{$interval} weeks");
                    break;
                
                case 'monthly':
                    $current_date->modify("+{$interval} months");
                    break;
                
                case 'yearly':
                    $current_date->modify("+{$interval} years");
                    break;
            }
        }

        return $dates;
    }

    /**
     * Check if a booking conflicts with existing bookings
     * 
     * @param int $room_id Room ID
     * @param string $date Date
     * @param string $start_time Start time
     * @param string $end_time End time
     * @param int $exclude_booking_id Booking ID to exclude from check
     * @return bool True if conflict exists
     */
    private function check_booking_conflict($room_id, $date, $start_time, $end_time, $exclude_booking_id = null) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}myvh_bookings 
             WHERE RoomId = %d 
             AND StartDate = %s 
             AND Status != 'cancelled'
             AND (
                 (StartTime < %s AND EndTime > %s) OR
                 (StartTime < %s AND EndTime > %s) OR
                 (StartTime >= %s AND EndTime <= %s)
             )",
            $room_id,
            $date,
            $end_time, $start_time,  // Ends after our start
            $end_time, $start_time,  // Starts before our end
            $start_time, $end_time   // Fully contained
        );

        if ($exclude_booking_id) {
            $sql .= $wpdb->prepare(" AND Id != %d", $exclude_booking_id);
        }

        $conflicts = $wpdb->get_var($sql);

        return $conflicts > 0;
    }

    public function delete($id) {
        // When deleting a pattern, optionally delete all associated future bookings
        // For now, we just delete the pattern
        return $this->repo->delete($id);
    }

    public function deactivate($id) {
        return $this->repo->deactivate($id);
    }

    /**
     * Delete all future bookings for a pattern
     * 
     * @param int $pattern_id Pattern ID
     * @return bool|WP_Error
     */
    public function delete_future_bookings($pattern_id) {
        global $wpdb;

        $today = date('Y-m-d');

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}myvh_bookings 
             WHERE RecurringPatternId = %d 
             AND StartDate > %s",
            $pattern_id,
            $today
        ));

        if ($result === false) {
            return new WP_Error('database', __('Failed to delete future bookings', 'my-village-hall'));
        }

        return true;
    }
}