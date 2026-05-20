<?php
namespace MYVH\Events;

use MYVH\Core\Logging\LoggerFactory;
use MYVH\Core\Scheduling\OvernightBatchRunner;
use MYVH\Core\Scheduling\OvernightJobScheduler;

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
        $this->update_logger_level($this->extract_logger_level($payload));
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
        $logger = LoggerFactory::refresh();

        $resolved_level = $this->resolve_level_name($level_name);

        try {
            $logger->emergency(
                'Logger level changed',
                [
                    'logger_level' => $resolved_level,
                ]
            );
        } catch (\Throwable $e) {
            // Never break settings saves due to audit logging failures.
        }
    }

    private function resolve_level_name(?string $level_name): string {
        $candidate = $level_name;

        if ($candidate === null || trim($candidate) === '') {
            $configured_level = myvh_setting('admin.logger_level', 'debug');
            $candidate = is_string($configured_level) ? $configured_level : 'debug';
        }

        $normalized = strtolower(trim($candidate));
        return $normalized !== '' ? $normalized : 'debug';
    }

    private function extract_logger_level($payload): ?string {
        if (!is_array($payload)) {
            return null;
        }

        $settings = $payload['settings'] ?? null;

        if (is_array($settings) && isset($settings['logger_level']) && is_scalar($settings['logger_level'])) {
            return (string) $settings['logger_level'];
        }

        // Support alternate payload formats used by some callers.
        if (isset($payload['logger_level']) && is_scalar($payload['logger_level'])) {
            return (string) $payload['logger_level'];
        }

        if (is_array($settings)) {
            $admin_settings = $settings['admin'] ?? null;
            if (is_array($admin_settings) && isset($admin_settings['logger_level']) && is_scalar($admin_settings['logger_level'])) {
                return (string) $admin_settings['logger_level'];
            }
        }

        return null;
    }
}