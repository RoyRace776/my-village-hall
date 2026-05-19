<?php

namespace MYVH\Bookings\Services;

use MYVH\Bookings\BookingChargeRepository;
use MYVH\Pricing\PricingService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WP_Error;

if (!defined('ABSPATH')) exit;

class BookingChargeService
{
    private $pricing;
    private $booking_charge_repo;
    private LoggerInterface $logger;

    public function __construct(PricingService $pricing, BookingChargeRepository $booking_charge_repo, ?LoggerInterface $logger = null)
    {
        $this->pricing = $pricing;
        $this->booking_charge_repo = $booking_charge_repo;
        $this->logger = $logger ?? new NullLogger();
    }

    public function recalculate($booking_id): bool|WP_Error
    {
        $booking_id = \intval($booking_id);
        if ($booking_id <= 0) {
            return new WP_Error('validation', __('Invalid booking id for charge recalculation', 'my-village-hall'));
        }

        $snapshot = $this->pricing->get_charge_snapshot($booking_id);
        if (is_wp_error($snapshot)) {
            return $snapshot;
        }

        $deleted = $this->booking_charge_repo->delete_by_booking_id($booking_id);
        if ($deleted === false) {
            return new WP_Error('database', __('Failed to clear previous booking charge rows', 'my-village-hall'));
        }

        $created = $this->booking_charge_repo->create($snapshot);
        if ($created === false) {
            return new WP_Error('database', __('Failed to save booking charge snapshot', 'my-village-hall'));
        }

        return true;
    }
}
