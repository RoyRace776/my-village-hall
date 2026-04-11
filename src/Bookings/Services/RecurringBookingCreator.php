<?php

namespace MYVH\Bookings\Services;

use MYVH\Bookings\RecurringPatternService;
use MYVH\Events\BookingEvents;
use MYVH\Events\EventDispatcher;
use WP_Error;

if (!defined('ABSPATH')) exit;

class RecurringBookingCreator
{
    private $recurring_pattern_service;

    public function __construct(RecurringPatternService $recurring_pattern_service)
    {
        $this->recurring_pattern_service = $recurring_pattern_service;
    }

    public function create_for_booking(int $booking_id, array $data): array|WP_Error
    {
        if (empty($data['is_recurring'])) {
            return [];
        }

        EventDispatcher::dispatch(
            BookingEvents::BEFORE_RECURRING,
            [
                'parent_booking_id' => $booking_id,
                'data' => $data,
            ]
        );

        $pattern_data = [
            'parent_booking_id'   => $booking_id,
            'recurrence_type'     => $data['recurrence_type'] ?? '',
            'recurrence_interval' => $data['recurrence_interval'] ?? 1,
            'recurrence_day'      => $data['recurrence_day'] ?? '',
            'recurrence_week'     => $data['recurrence_week'] ?? '',
            'start_date'          => $data['start_date'] ?? '',
            'is_active'           => 1,
        ];

        if (($data['recurrence_end_type'] ?? '') === 'date') {
            $pattern_data['end_date'] = $data['recurrence_end_date'] ?? '';
        } else {
            $pattern_data['max_occurrences'] = $data['max_occurrences'] ?? null;
        }

        $pattern_result = $this->recurring_pattern_service->save($pattern_data);
        if (is_wp_error($pattern_result)) {
            return $pattern_result;
        }

        $warnings = [];
        $booking_results = $this->recurring_pattern_service->get_last_booking_results();
        if (is_array($booking_results)) {
            if (!empty($booking_results['conflicts'])) {
                $warnings[] = sprintf(
                    __('Some recurring bookings were not created due to conflicts: %s', 'my-village-hall'),
                    implode(', ', $booking_results['conflicts'])
                );
            }

            if (!empty($booking_results['errors'])) {
                $warnings[] = implode(' ', $booking_results['errors']);
            }
        }

        EventDispatcher::dispatch(
            BookingEvents::AFTER_RECURRING,
            [
                'parent_booking_id' => $booking_id,
                'data' => $data,
            ]
        );

        return $warnings;
    }
}
