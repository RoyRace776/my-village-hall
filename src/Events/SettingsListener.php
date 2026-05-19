<?php
namespace MYVH\Events;

use MYVH\Container\Container;
use MYVH\Core\Logging\LoggerFactory;
use MYVH\Core\Scheduling\OvernightBatchRunner;
use MYVH\Core\Scheduling\OvernightJobScheduler;
use Psr\Log\LoggerInterface;

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
        $level_name = null;

        if (is_array($payload)) {
            $settings = $payload['settings'] ?? null;
            if (is_array($settings) && isset($settings['logger_level']) && is_scalar($settings['logger_level'])) {
                $level_name = (string) $settings['logger_level'];
            }
        }

        $this->update_logger_level($level_name);
    }

    public function handle_booking_settings_saved($payload): void {
    }

    public function handle_calendar_settings_saved($payload): void {
    }

    public function handle_general_settings_saved($payload): void {
    }

    public function handle_invoicing_settings_saved($payload): void {
        $run_overnight = (bool) myvh_setting( 'invoicing.run_overnight', false );

        if ( $run_overnight ) {
            OvernightJobScheduler::schedule( OvernightBatchRunner::HOOK );
            return;
        }

        OvernightJobScheduler::clear( OvernightBatchRunner::HOOK );
    }

    public function handle_notices_settings_saved($payload): void {
    }

    private function update_logger_level(?string $level_name = null): void {
        global $myvh_container;

        if (!$myvh_container instanceof Container) {
            return;
        }

        try {
            $logger = $myvh_container->get(LoggerInterface::class);
        } catch (\Throwable $e) {
            return;
        }

        LoggerFactory::update_level_from_settings($logger, $level_name);
    }
}