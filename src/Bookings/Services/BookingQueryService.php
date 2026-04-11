<?php

namespace MYVH\Bookings\Services;

use MYVH\Bookings\BookingRepository;
use MYVH\Bookings\BookingStatus;
use MYVH\Customers\CustomerRepository;

if (!defined('ABSPATH')) exit;

class BookingQueryService
{
    private $booking_repo;
    private $customer_repo;

    public function __construct(BookingRepository $booking_repo, CustomerRepository $customer_repo)
    {
        $this->booking_repo = $booking_repo;
        $this->customer_repo = $customer_repo;
    }

    public function get_all_with_details($args = []): array
    {
        return $this->booking_repo->get_all_with_details($args);
    }

    public function get_by_id_with_details(int $booking_id): ?array
    {
        if ($booking_id <= 0) {
            return null;
        }

        $results = $this->booking_repo->get_all_with_details([
            'booking_id' => $booking_id,
        ]);

        return $results[0] ?? null;
    }

    public function get_by_id_with_details_for_customer(int $booking_id, int $customer_id): ?array
    {
        if ($booking_id <= 0 || $customer_id <= 0) {
            return null;
        }

        $results = $this->booking_repo->get_all_with_details([
            'booking_id' => $booking_id,
            'customer_id' => $customer_id,
        ]);

        return $results[0] ?? null;
    }

    public function get_booking_list($filters = []): array
    {
        $defaults = [
            'status'      => '',
            'room_id'     => 0,
            'customer_id' => 0,
        ];

        $filters = wp_parse_args($filters, $defaults);

        $query_args = [
            'orderby'     => 'b.StartDate',
            'order'       => 'DESC',
            'status'      => $filters['status'],
            'room_id'     => $filters['room_id'],
            'customer_id' => $filters['customer_id'],
        ];

        $bookings = $this->booking_repo->get_all_with_details($query_args);
        $groups = $this->group_bookings($bookings);

        return [
            'groups'           => $groups,
            'total'            => count($bookings),
            'recurring_groups' => count(array_filter($groups, fn($g) => $g['type'] === 'recurring')),
        ];
    }

    public function get_between($start, $end, $context = null, $filters = []): array
    {
        return $this->booking_repo->get_between($start, $end, $context, $filters);
    }

    public function get_upcoming_bookings($wp_user): array
    {
        $customer_id = $this->customer_repo->get_customer_id($wp_user);

        return $this->booking_repo->get_upcoming_bookings($customer_id);
    }

    private function group_bookings($bookings): array
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

    private function find_next_booking($bookings, $today): ?array
    {
        foreach (array_reverse($bookings) as $booking) {
            if ($booking['StartDate'] >= $today && $booking['Status'] !== BookingStatus::CANCELLED) {
                return $booking;
            }
        }

        return null;
    }

    private function determine_group_status($members): string
    {
        $statuses = array_column($members, 'Status');

        foreach ($statuses as $status) {
            if ($status === BookingStatus::CONFIRMED) {
                return BookingStatus::CONFIRMED;
            }
        }

        foreach ($statuses as $status) {
            if ($status === BookingStatus::PENDING) {
                return BookingStatus::PENDING;
            }
        }

        return $statuses[0] ?? BookingStatus::COMPLETED;
    }
}
