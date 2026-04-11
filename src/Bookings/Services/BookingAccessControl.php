<?php

namespace MYVH\Bookings\Services;

use MYVH\Bookings\BookingRepository;
use MYVH\Bookings\BookingStatus;
use MYVH\Organisations\OrganisationRepository;

if (!defined('ABSPATH')) exit;

class BookingAccessControl
{
    private $booking_repo;
    private $organisation_repo;

    public function __construct(BookingRepository $booking_repo, OrganisationRepository $organisation_repo)
    {
        $this->booking_repo = $booking_repo;
        $this->organisation_repo = $organisation_repo;
    }

    public function resolve_public_visibility($data, $organisation_id, $booking_id): int
    {
        if (array_key_exists('public', $data)) {
            return !empty($data['public']) ? 1 : 0;
        }

        if ($booking_id > 0) {
            $existing = $this->booking_repo->get_by_id($booking_id);
            if (is_array($existing) && array_key_exists('Public', $existing)) {
                return !empty($existing['Public']) ? 1 : 0;
            }
        }

        if ($organisation_id > 0) {
            $organisation = $this->organisation_repo->get_by_id($organisation_id);
            if (is_array($organisation) && array_key_exists('DefaultPublic', $organisation)) {
                return !empty($organisation['DefaultPublic']) ? 1 : 0;
            }
        }

        return 0;
    }

    public function can_delete($booking): array
    {
        if (empty($booking) || empty($booking['StartDate']) || empty($booking['StartTime'])) {
            return [
                'can_delete' => false,
                'reason' => 'Booking details are incomplete.',
            ];
        }

        $status = strtolower((string) ($booking['Status'] ?? ''));
        $start_ts = strtotime((string) $booking['StartDate'] . ' ' . (string) $booking['StartTime']);
        $now_ts = current_time('timestamp');
        $min_notice_hours = max(0, intval(myvh_setting('booking.min_notice_hours', 24)));
        $min_notice_days = (int) ceil($min_notice_hours / 24);

        if (!$start_ts || $start_ts <= $now_ts) {
            return [
                'can_delete' => false,
                'reason' => 'Past bookings cannot be deleted.',
                'min_notice_hours' => $min_notice_hours,
                'min_notice_days' => $min_notice_days,
            ];
        }

        if ($status === BookingStatus::PENDING || $status === strtolower(BookingStatus::PENDING)) {
            return [
                'can_delete' => true,
                'reason' => '',
                'min_notice_hours' => $min_notice_hours,
                'min_notice_days' => $min_notice_days,
            ];
        }

        if ($status === BookingStatus::CONFIRMED || $status === strtolower(BookingStatus::CONFIRMED)) {
            $threshold_ts = $now_ts + ($min_notice_hours * 3600);

            if ($start_ts > $threshold_ts) {
                return [
                    'can_delete' => true,
                    'reason' => '',
                    'min_notice_hours' => $min_notice_hours,
                    'min_notice_days' => $min_notice_days,
                ];
            }

            return [
                'can_delete' => false,
                'reason' => sprintf(
                    'Confirmed bookings can only be deleted when more than %d day(s) away.',
                    $min_notice_days
                ),
                'min_notice_hours' => $min_notice_hours,
                'min_notice_days' => $min_notice_days,
            ];
        }

        return [
            'can_delete' => false,
            'reason' => 'Only pending or confirmed bookings can be deleted.',
            'min_notice_hours' => $min_notice_hours,
            'min_notice_days' => $min_notice_days,
        ];
    }

    public function can_edit($booking): array
    {
        return [
            'can_edit' => true,
            'reason' => '',
        ];
    }
}
