<?php

namespace MYVH\Tests\Unit;

use Brain\Monkey;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use MYVH\Tests\stubs\Event_Dispatcher;
use MYVH\Tests\BookingStatus;

/**
 * Base class for all MYVH unit tests.
 *
 * Sets up Brain Monkey (which stubs core WP functions) and Mockery before
 * each test, and tears them down cleanly after.
 */
abstract class Unit_Test_Case extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Event_Dispatcher::reset();

        // Stub the WP functions most commonly used in the booking service so
        // tests that do not care about them don't need to set expectations.
        Monkey\Functions\stubs([
            'sanitize_text_field'     => fn($v) => (string) $v,
            'sanitize_textarea_field' => fn($v) => (string) $v,
            '__'                      => fn($text) => $text,
            'wp_parse_args'           => fn($args, $defaults) => array_merge($defaults, (array) $args),
            'myvh_setting'            => false,
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Assert that a given event was dispatched at least once.
     */
    protected function assertEventDispatched(string $event, ?array $expected_data = null): void {
        $this->assertTrue(
            Event_Dispatcher::was_dispatched($event),
            "Expected event [{$event}] to have been dispatched."
        );

        if ($expected_data !== null) {
            $calls = Event_Dispatcher::get_dispatched($event);
            $matched = array_filter($calls, function ($call) use ($expected_data) {
                foreach ($expected_data as $key => $value) {
                    if (($call['data'][$key] ?? null) !== $value) {
                        return false;
                    }
                }
                return true;
            });

            $this->assertNotEmpty(
                $matched,
                "Event [{$event}] was dispatched but not with the expected data: " . json_encode($expected_data)
            );
        }
    }

    /**
     * Assert that a given event was never dispatched.
     */
    protected function assertEventNotDispatched(string $event): void {
        $this->assertFalse(
            Event_Dispatcher::was_dispatched($event),
            "Expected event [{$event}] NOT to have been dispatched."
        );
    }

    /**
     * Create a Mockery mock for a given class.
     *
     * @template T
     * @param class-string<T> $class
     * @return T&MockInterface
     */
    protected function mock(string $class): MockInterface {
        return Mockery::mock($class);
    }
}
