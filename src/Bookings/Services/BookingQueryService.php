<?php

namespace MYVH\Bookings\Services;

use MYVH\Bookings\Booking;
use MYVH\Bookings\BookingRepository;
use MYVH\Customers\CustomerRepository;

if (!defined('ABSPATH')) exit;

class BookingQueryService
{
    private $booking_repo;
    private $customer_repo;
    private $booking_list_grouping_service;

    public function __construct(
        BookingRepository $booking_repo,
        CustomerRepository $customer_repo,
        BookingListGroupingService $booking_list_grouping_service
    )
    {
        $this->booking_repo = $booking_repo;
        $this->customer_repo = $customer_repo;
        $this->booking_list_grouping_service = $booking_list_grouping_service;
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
        $groups = $this->booking_list_grouping_service->group_bookings($bookings);

        return [
            'groups'           => $groups,
            'total'            => count($bookings),
            'recurring_groups' => count(array_filter($groups, fn($g) => $g['type'] === 'recurring')),
        ];
    }

    public function get_between( mixed $start, mixed $end, mixed $context = null, mixed $filters = []): array
    {
        return $this->booking_repo->get_between($start, $end, $context, $filters);
    }

    public function get_upcoming_bookings($wp_user): array
    {
        $customer_id = $this->customer_repo->get_customer_id($wp_user);

        return $this->booking_repo->get_upcoming_bookings($customer_id);
    }

}
