<?php

namespace MYVH\Bookings\Services;

use MYVH\Bookings\BookingRepository;
use MYVH\Bookings\RecurringPatternService;
use WP_Error;

if (!defined('ABSPATH')) exit;

class RecurringBookingUpdater
{
    private const EDIT_SCOPE_THIS_ONLY = 'this_only';
    private const EDIT_SCOPE_ALL_BOOKINGS = 'all_bookings';
    private const EDIT_SCOPE_THIS_AND_FUTURE = 'this_and_future';

    private $booking_repo;
    private $recurring_pattern_service;

    public function __construct(BookingRepository $booking_repo, RecurringPatternService $recurring_pattern_service)
    {
        $this->booking_repo = $booking_repo;
        $this->recurring_pattern_service = $recurring_pattern_service;
    }

    public function update_with_scope(
        array $data,
        array $record,
        array $current_record,
        int $pattern_id,
        string $edit_scope,
        callable $apply_single_booking_update
    ): int|WP_Error {
        $schedule_change_error = $this->validate_series_safe_changes($data, $current_record);
        if (is_wp_error($schedule_change_error)) {
            return $schedule_change_error;
        }

        $bookings = $this->get_sorted_pattern_bookings($pattern_id);
        if (empty($bookings)) {
            return new WP_Error('not_found', __('Recurring booking series not found', 'my-village-hall'));
        }

        if ($edit_scope === self::EDIT_SCOPE_ALL_BOOKINGS) {
            return $this->apply_scoped_updates_to_bookings($bookings, $data, $record, $apply_single_booking_update);
        }

        $selected_index = $this->find_booking_index($bookings, \intval($data['booking_id'] ?? 0));
        if ($selected_index < 0) {
            return new WP_Error('not_found', __('Booking not found in recurring series', 'my-village-hall'));
        }

        if ($selected_index === 0) {
            return $this->apply_scoped_updates_to_bookings($bookings, $data, $record, $apply_single_booking_update);
        }

        $split_result = $this->recurring_pattern_service->split_pattern_from_booking($pattern_id, \intval($data['booking_id'] ?? 0));
        if (is_wp_error($split_result)) {
            return $split_result;
        }

        return $this->apply_scoped_updates_to_bookings(
            $split_result['future_bookings'] ?? [],
            $data,
            $record,
            $apply_single_booking_update,
            \intval($split_result['new_pattern_id'] ?? 0)
        );
    }

    private function apply_scoped_updates_to_bookings(
        array $bookings,
        array $base_data,
        array $base_record,
        callable $apply_single_booking_update,
        int $override_pattern_id = 0
    ): int|WP_Error {
        if (empty($bookings)) {
            return new WP_Error('validation', __('No recurring bookings matched the selected scope', 'my-village-hall'));
        }

        foreach ($bookings as $booking) {
            $booking_id = \intval($booking['Id'] ?? 0);
            if ($booking_id <= 0) {
                continue;
            }

            $data = $this->build_series_update_data_for_booking($booking, $base_data, $base_record);
            $record = $this->build_series_update_record($base_record, $override_pattern_id);
            $current_record = $booking;

            if ($override_pattern_id > 0) {
                $current_record['RecurringPatternId'] = $override_pattern_id;
            }

            $result = $apply_single_booking_update($booking_id, $data, $record, $current_record);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        return \intval($base_data['booking_id'] ?? 0);
    }

    private function build_series_update_data_for_booking(array $booking, array $base_data, array $base_record): array
    {
        $data = [
            'booking_id' => \intval($booking['Id'] ?? 0),
            'customer_id' => \intval($booking['CustomerId'] ?? 0),
            'organisation_id' => \intval($booking['OrganisationId'] ?? 0),
            'room_id' => \intval($booking['RoomId'] ?? 0),
            'start_date' => sanitize_text_field($booking['StartDate'] ?? ''),
            'end_date' => sanitize_text_field($booking['EndDate'] ?? ($booking['StartDate'] ?? '')),
            'start_time' => sanitize_text_field($booking['StartTime'] ?? ''),
            'end_time' => sanitize_text_field($booking['EndTime'] ?? ''),
            'status' => sanitize_text_field((string) ($base_record['Status'] ?? $booking['Status'] ?? '')),
            'description' => sanitize_textarea_field($base_record['Description'] ?? $booking['Description'] ?? ''),
            'public' => \intval($base_record['Public'] ?? $booking['Public'] ?? 0),
            'no_invoice_required' => \intval($base_record['NoInvoiceRequired'] ?? $booking['NoInvoiceRequired'] ?? 0),
            'edit_scope' => $this->normalize_edit_scope($base_data['edit_scope'] ?? ''),
        ];

        if (array_key_exists('addons', $base_data)) {
            $data['addons'] = $base_data['addons'];
        }

        return $data;
    }

    private function build_series_update_record(array $base_record, int $override_pattern_id = 0): array
    {
        $record = [
            'Status' => $base_record['Status'],
            'Description' => $base_record['Description'],
            'Public' => $base_record['Public'],
            'NoInvoiceRequired' => \intval($base_record['NoInvoiceRequired'] ?? 0),
        ];

        if ($override_pattern_id > 0) {
            $record['RecurringPatternId'] = $override_pattern_id;
        }

        return $record;
    }

    private function validate_series_safe_changes(array $data, array $current_record): bool|WP_Error
    {
        $sensitive_fields = [
            'customer_id' => 'customer',
            'organisation_id' => 'organisation',
            'room_id' => 'room',
            'start_date' => 'start date',
            'end_date' => 'end date',
            'start_time' => 'start time',
            'end_time' => 'end time',
        ];

        $changed_fields = [];

        foreach ($sensitive_fields as $field => $label) {
            $existing_key = match ($field) {
                'customer_id' => 'CustomerId',
                'organisation_id' => 'OrganisationId',
                'room_id' => 'RoomId',
                'start_date' => 'StartDate',
                'end_date' => 'EndDate',
                'start_time' => 'StartTime',
                'end_time' => 'EndTime',
                default => '',
            };

            $incoming = isset($data[$field]) ? (string) $data[$field] : '';
            $existing = isset($current_record[$existing_key]) ? (string) $current_record[$existing_key] : '';

            if ($incoming !== '' && $incoming !== $existing) {
                $changed_fields[] = $label;
            }
        }

        if (!empty($changed_fields)) {
            return new WP_Error(
                'validation',
                sprintf(
                    __('This recurring update can only change description, status, visibility, and add-ons. Use "This booking only" for %s.', 'my-village-hall'),
                    implode(', ', $changed_fields)
                )
            );
        }

        return true;
    }

    private function get_sorted_pattern_bookings(int $pattern_id): array
    {
        $bookings = $this->booking_repo->get_by_pattern_id($pattern_id);

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

        return $bookings;
    }

    private function find_booking_index(array $bookings, int $booking_id): int
    {
        foreach ($bookings as $index => $booking) {
            if (intval($booking['Id'] ?? 0) === $booking_id) {
                return $index;
            }
        }

        return -1;
    }

    private function normalize_edit_scope($scope): string
    {
        $value = sanitize_text_field((string) $scope);

        if ($value === self::EDIT_SCOPE_ALL_BOOKINGS || $value === self::EDIT_SCOPE_THIS_AND_FUTURE) {
            return $value;
        }

        return self::EDIT_SCOPE_THIS_ONLY;
    }
}
