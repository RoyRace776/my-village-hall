<?php

namespace MYVH\Bookings\Services;

use MYVH\Addons\AddonService;
use MYVH\Events\BookingEvents;
use MYVH\Events\EventDispatcher;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if (!defined('ABSPATH')) exit;

class BookingAddonSyncService
{
    private $addon_service;
    private LoggerInterface $logger;

    public function __construct(AddonService $addon_service, ?LoggerInterface $logger = null)
    {
        $this->addon_service = $addon_service;
        $this->logger = $logger ?? new NullLogger();
    }

    public function sync(int $booking_id, array $addons, bool $replace): void
    {
        EventDispatcher::dispatch(
            BookingEvents::BEFORE_ADDONS_CHANGE,
            [
                'booking_id' => $booking_id,
                'addons' => $addons,
                'replace' => $replace,
            ]
        );

        $this->addon_service->save_booking_addons($booking_id, $addons, $replace);

        EventDispatcher::dispatch(
            BookingEvents::AFTER_ADDONS_CHANGE,
            [
                'booking_id' => $booking_id,
                'addons' => $addons,
                'replace' => $replace,
            ]
        );
    }
}
