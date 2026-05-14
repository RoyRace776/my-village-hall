<?php
namespace MYVH\Events;

class SettingsEvents {
    const ADMIN_SAVED = 'settings.admin.saved';
    const BOOKING_SAVED = 'settings.booking.saved';
    const CALENDAR_SAVED = 'settings.calendar.saved';
    const GENERAL_SAVED = 'settings.general.saved';
    const INVOICING_SAVED = 'settings.invoicing.saved';
    const NOTICES_SAVED = 'settings.notices.saved';

    public static function from_group(string $group): ?string {
        return match ($group) {
            'admin' => self::ADMIN_SAVED,
            'booking' => self::BOOKING_SAVED,
            'calendar' => self::CALENDAR_SAVED,
            'general' => self::GENERAL_SAVED,
            'invoicing' => self::INVOICING_SAVED,
            'notices' => self::NOTICES_SAVED,
            default => null,
        };
    }
}