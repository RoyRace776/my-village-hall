<?php

namespace MYVH\Bookings\Services;

use MYVH\Bookings\BookingRepository;
use MYVH\Bookings\BookingStatus;
use MYVH\Customers\CustomerRepository;
use MYVH\Organisations\OrganisationMemberRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Portal\ClientAdminService;

if (!defined('ABSPATH')) exit;

class BookingAccessControl
{
    private $booking_repo;
    private $organisation_repo;
    private $customer_repo;
    private $organisation_member_repo;
    private $client_admin_service;

    public function __construct(
        BookingRepository $booking_repo,
        OrganisationRepository $organisation_repo,
        CustomerRepository $customer_repo,
        OrganisationMemberRepository $organisation_member_repo,
        ClientAdminService $client_admin_service
    )
    {
        $this->booking_repo = $booking_repo;
        $this->organisation_repo = $organisation_repo;
        $this->customer_repo = $customer_repo;
        $this->organisation_member_repo = $organisation_member_repo;
        $this->client_admin_service = $client_admin_service;
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
        $min_notice_hours = max(0, \intval(myvh_setting('booking.min_notice_hours', 24)));
        $min_notice_days = (int) ceil($min_notice_hours / 24);

        // Pending bookings can always be deleted, regardless of date.
        if ($status === BookingStatus::PENDING->value) {
            return [
                'can_delete' => true,
                'reason' => '',
                'min_notice_hours' => $min_notice_hours,
                'min_notice_days' => $min_notice_days,
            ];
        }

        if (!$start_ts || $start_ts <= $now_ts) {
            return [
                'can_delete' => false,
                'reason' => 'Past bookings cannot be deleted.',
                'min_notice_hours' => $min_notice_hours,
                'min_notice_days' => $min_notice_days,
            ];
        }

        if ($status === BookingStatus::CONFIRMED->value) {
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
        $booking_id = \intval($booking['Id'] ?? 0);
        $status = strtolower((string) ($booking['Status'] ?? ''));
        $current_user_id = \intval(get_current_user_id());
        $current_blog_id = \intval(get_current_blog_id());
        $is_client_admin = $this->client_admin_service->can_administer_blog($current_user_id, $current_blog_id);

        if ($booking_id > 0 && $this->booking_repo->has_invoiced_items($booking_id)) {
            return [
                'can_edit' => false,
                'reason' => 'Invoiced bookings cannot be edited.',
            ];
        }

        if ($status === BookingStatus::CONFIRMED->value || $status === BookingStatus::CANCELLED->value) {
            return [
                'can_edit' => $is_client_admin,
                'reason' => $is_client_admin ? '' : 'Only client administrators can edit confirmed or cancelled bookings.',
            ];
        }

        if ($status !== BookingStatus::PENDING->value) {
            return [
                'can_edit' => false,
                'reason' => 'This booking cannot be edited.',
            ];
        }

        if ($is_client_admin) {
            return [
                'can_edit' => true,
                'reason' => '',
            ];
        }

        $current_customer = $current_user_id > 0 ? $this->customer_repo->get_by_user_id($current_user_id) : null;
        $current_customer_id = \intval($current_customer['Id'] ?? 0);
        $booker_customer_id = \intval($booking['CustomerId'] ?? 0);
        $organisation_id = \intval($booking['OrganisationId'] ?? 0);

        if ($current_customer_id > 0 && $current_customer_id === $booker_customer_id) {
            return [
                'can_edit' => true,
                'reason' => '',
            ];
        }

        if (
            $current_customer_id > 0
            && $organisation_id > 0
            && $this->organisation_member_repo->is_customer_admin($organisation_id, $current_customer_id)
        ) {
            return [
                'can_edit' => true,
                'reason' => '',
            ];
        }

        return [
            'can_edit' => false,
            'reason' => 'Only the booker, an organisation admin, or a client administrator can edit pending bookings.',
        ];
    }
}
