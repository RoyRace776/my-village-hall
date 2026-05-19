<?php

namespace MYVH\Bookings\Services;

use Exception;
use MYVH\Bookings\BookingAddonRepository;
use MYVH\Bookings\BookingChargeRepository;
use MYVH\Bookings\BookingRepository;
use MYVH\Events\BookingEvents;
use MYVH\Events\EventDispatcher;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WP_Error;

if (!defined('ABSPATH')) exit;

class BookingDeletionService
{
    private $booking_repo;
    private $booking_addon_repo;
    private $booking_charge_repo;
    private LoggerInterface $logger;

    public function __construct(
        BookingRepository $booking_repo,
        BookingAddonRepository $booking_addon_repo,
        BookingChargeRepository $booking_charge_repo,
        ?LoggerInterface $logger = null
    ) {
        $this->booking_repo = $booking_repo;
        $this->booking_addon_repo = $booking_addon_repo;
        $this->booking_charge_repo = $booking_charge_repo;
        $this->logger = $logger ?? new NullLogger();
    }

    public function delete($id): bool|WP_Error
    {
        $booking_id = \intval($id);
        if ($booking_id <= 0) {
            return new WP_Error('validation', __('Booking ID is required', 'my-village-hall'));
        }

        $this->booking_repo->begin();

        try {
            EventDispatcher::dispatch(
                BookingEvents::BEFORE_DELETE,
                ['booking_id' => $booking_id]
            );

            if ($this->booking_addon_repo && !$this->booking_addon_repo->delete_by_booking_id($booking_id)) {
                $this->booking_repo->rollback();
                return new WP_Error('database', __('Failed to remove booking addons', 'my-village-hall'));
            }

            if ($this->booking_charge_repo && !$this->booking_charge_repo->delete_by_booking_id($booking_id)) {
                $this->booking_repo->rollback();
                return new WP_Error('database', __('Failed to remove booking charges', 'my-village-hall'));
            }

            $deleted = $this->booking_repo->delete($booking_id);
            if (!$deleted) {
                $this->booking_repo->rollback();
                return new WP_Error('database', __('Failed to delete booking', 'my-village-hall'));
            }

            EventDispatcher::dispatch(
                BookingEvents::DELETED,
                ['booking_id' => $booking_id]
            );
            EventDispatcher::dispatch(
                BookingEvents::AFTER_DELETE,
                ['booking_id' => $booking_id]
            );

            $this->booking_repo->commit();
            return true;
        } catch (Exception $e) {
            $this->booking_repo->rollback();
            return new WP_Error('transaction', __('A database error occurred: ', 'my-village-hall') . $e->getMessage());
        }
    }
}
