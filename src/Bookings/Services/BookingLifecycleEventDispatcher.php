<?php

namespace MYVH\Bookings\Services;

use MYVH\Events\BookingEvents;
use MYVH\Events\EventDispatcher;

if (!defined('ABSPATH')) exit;

class BookingLifecycleEventDispatcher
{
    public function dispatch_before_save(array $data): void
    {
        if (!empty($data['booking_id'])) {
            EventDispatcher::dispatch(BookingEvents::BEFORE_UPDATE, $data);
            return;
        }

        EventDispatcher::dispatch(BookingEvents::BEFORE_CREATE, $data);
    }

    public function dispatch_after_update(array $data): void
    {
        EventDispatcher::dispatch(BookingEvents::AFTER_UPDATE, $data);
    }
}
