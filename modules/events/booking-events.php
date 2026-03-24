<?php

class MYVH_Booking_Events {

    // Booking lifecycle events
    const BEFORE_CREATE = 'booking.before_create';
    const CREATED = 'booking.created';
    const AFTER_CREATE = 'booking.after_create';

    const BEFORE_UPDATE = 'booking.before_update';
    const UPDATED = 'booking.updated';
    const AFTER_UPDATE = 'booking.after_update';

    const BEFORE_DELETE = 'booking.before_delete';
    const DELETED = 'booking.deleted';
    const AFTER_DELETE = 'booking.after_delete';

    // Status transitions
    const BEFORE_STATUS_CHANGE = 'booking.before_status_change';
    const STATUS_CHANGED = 'booking.status_changed';
    const AFTER_STATUS_CHANGE = 'booking.after_status_change';

    const CANCELLED = 'booking.cancelled';
    const CONFIRMED = 'booking.confirmed';
    const COMPLETED = 'booking.completed';

    // Recurring pattern
    const BEFORE_RECURRING = 'booking.before_recurring';
    const AFTER_RECURRING = 'booking.after_recurring';

    // Addons
    const BEFORE_ADDONS_CHANGE = 'booking.before_addons_change';
    const AFTER_ADDONS_CHANGE = 'booking.after_addons_change';

}