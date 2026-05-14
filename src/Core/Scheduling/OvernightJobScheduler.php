<?php
namespace MYVH\Core\Scheduling;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Static utility for managing WP-Cron overnight events.
 *
 * Keeps scheduling logic in one place so multiple callers (runner, plugin
 * deactivation hook) do not duplicate wp_schedule_event / wp_clear_scheduled_hook calls.
 */
class OvernightJobScheduler {

    /**
     * Returns the next Unix timestamp at which 04:00 site-local time will occur.
     * Always returns a future timestamp (at least a moment from now).
     */
    public static function compute_next_4am(): int {
        $tz  = wp_timezone();
        $now = new \DateTimeImmutable( 'now', $tz );

        // Build 04:00 today in site-local time.
        $candidate = $now->setTime( 4, 0, 0 );

        // If 04:00 has already passed today, move to tomorrow.
        if ( $candidate <= $now ) {
            $candidate = $candidate->modify( '+1 day' );
        }

        return $candidate->getTimestamp();
    }

    /**
     * Schedule the hook as a daily event at 04:00 site-local time if it is not
     * already scheduled.
     */
    public static function schedule( string $hook ): void {
        if ( ! wp_next_scheduled( $hook ) ) {
            wp_schedule_event( self::compute_next_4am(), 'daily', $hook );
        }
    }

    /**
     * Remove the scheduled hook (all occurrences).
     */
    public static function clear( string $hook ): void {
        wp_clear_scheduled_hook( $hook );
    }

    /**
     * Returns true when the hook has a scheduled future run.
     */
    public static function is_scheduled( string $hook ): bool {
        return (bool) wp_next_scheduled( $hook );
    }
}
