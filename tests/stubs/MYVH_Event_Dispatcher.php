<?php
namespace MYVH\Tests\stubs;
/**
 * Test stub for MYVH_Event_Dispatcher.
 *
 * Replaces the real static dispatcher during unit tests so no actual
 * event listeners fire. Calls are recorded and can be asserted against.
 *
 * Usage in tests:
 *
 *   // Assert an event was dispatched
 *   $this->assertEventDispatched(MYVH_Booking_Events::CREATED);
 *
 *   // Assert an event was dispatched with specific data
 *   $this->assertEventDispatched(MYVH_Booking_Events::CREATED, ['booking_id' => 42]);
 *
 *   // Reset between tests (called automatically in MYVH_Unit_Test_Case::setUp)
 *   MYVH_Event_Dispatcher::reset();
 */
class MYVH_Event_Dispatcher {

    private static array $dispatched = [];

    public static function dispatch(string $event, array $data = []): void {
        self::$dispatched[] = ['event' => $event, 'data' => $data];
    }

    /**
     * Return all dispatched events, optionally filtered by event name.
     */
    public static function get_dispatched(?string $event = null): array {
        if ($event === null) {
            return self::$dispatched;
        }

        return array_values(
            array_filter(self::$dispatched, fn($e) => $e['event'] === $event)
        );
    }

    /**
     * Returns true if the given event was dispatched at least once.
     */
    public static function was_dispatched(string $event): bool {
        return count(self::get_dispatched($event)) > 0;
    }

    /**
     * Clear all recorded events. Called in setUp() so tests don't bleed into each other.
     */
    public static function reset(): void {
        self::$dispatched = [];
    }
}
