<?php
namespace MYVH\Events;

class SettingsListener {
    public function register(): void {
        add_action('myvh_event_' . SettingsEvents::ADMIN_SAVED, [$this, 'handle_admin_settings_saved']);
        add_action('myvh_event_' . SettingsEvents::BOOKING_SAVED, [$this, 'handle_booking_settings_saved']);
        add_action('myvh_event_' . SettingsEvents::CALENDAR_SAVED, [$this, 'handle_calendar_settings_saved']);
        add_action('myvh_event_' . SettingsEvents::GENERAL_SAVED, [$this, 'handle_general_settings_saved']);
        add_action('myvh_event_' . SettingsEvents::INVOICING_SAVED, [$this, 'handle_invoicing_settings_saved']);
        add_action('myvh_event_' . SettingsEvents::NOTICES_SAVED, [$this, 'handle_notices_settings_saved']);
    }

    public function handle_admin_settings_saved($payload): void {
    }

    public function handle_booking_settings_saved($payload): void {
    }

    public function handle_calendar_settings_saved($payload): void {
    }

    public function handle_general_settings_saved($payload): void {
    }

    public function handle_invoicing_settings_saved($payload): void {
    }

    public function handle_notices_settings_saved($payload): void {
    }
}