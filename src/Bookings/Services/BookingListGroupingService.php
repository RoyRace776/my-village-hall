<?php

namespace MYVH\Bookings\Services;

use MYVH\Bookings\BookingStatus;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if (!defined('ABSPATH')) exit;

class BookingListGroupingService
{
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function group_bookings(array $bookings): array
    {
        $groups = [];
        $today = date('Y-m-d');

        foreach ($bookings as $booking) {
            if (!empty($booking['RecurringPatternId'])) {
                $key = 'r_' . $booking['RecurringPatternId'];

                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'type'     => 'recurring',
                        'pattern'  => [
                            'Id'                 => $booking['RecurringPatternId'],
                            'RecurrenceType'     => $booking['RecurrenceType'],
                            'RecurrenceInterval' => $booking['RecurrenceInterval'],
                            'RecurrenceDay'      => $booking['RecurrenceDay'],
                            'RecurrenceWeek'     => $booking['RecurrenceWeek'],
                            'StartDate'          => $booking['PatternStartDate'],
                            'EndDate'            => $booking['PatternEndDate'],
                            'IsActive'           => $booking['PatternIsActive'],
                        ],
                        'bookings' => [],
                    ];
                }

                $groups[$key]['bookings'][] = $booking;
                continue;
            }

            $groups['b_' . $booking['Id']] = [
                'type'     => 'standalone',
                'bookings' => [$booking],
            ];
        }

        foreach ($groups as &$group) {
            if ($group['type'] === 'recurring') {
                $group['next_booking'] = $this->find_next_booking($group['bookings'], $today);
                $group['status'] = $this->determine_group_status($group['bookings']);
                $group['count'] = count($group['bookings']);
                continue;
            }

            $booking = $group['bookings'][0];
            $group['next_booking'] = $booking;
            $group['status'] = $booking['Status'];
            $group['count'] = 1;
        }
        unset($group);

        return $groups;
    }

    private function find_next_booking(array $bookings, string $today): ?array
    {
        foreach (array_reverse($bookings) as $booking) {
            if ($booking['StartDate'] >= $today && $booking['Status'] !== BookingStatus::CANCELLED->value) {
                return $booking;
            }
        }

        return null;
    }

    private function determine_group_status(array $members): string
    {
        $statuses = array_column($members, 'Status');

        foreach ($statuses as $status) {
            if ($status === BookingStatus::CONFIRMED->value) {
                return BookingStatus::CONFIRMED->value;
            }
        }

        foreach ($statuses as $status) {
            if ($status === BookingStatus::PENDING->value) {
                return BookingStatus::PENDING->value;
            }
        }

        return $statuses[0] ?? BookingStatus::COMPLETED->value;
    }
}
