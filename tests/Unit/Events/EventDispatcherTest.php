<?php

namespace MYVH\Tests\Unit\Events;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Events\EventDispatcher;
use MYVH\Tests\Unit\UnitTestCase;

class EventDispatcherTest extends UnitTestCase
{
    /** @test */
    public function dispatch_calls_do_action_with_prefixed_event_name(): void
    {
        Functions\expect('do_action')
            ->once()
            ->with('myvh_event_booking.created', ['booking_id' => 1]);

        EventDispatcher::dispatch('booking.created', ['booking_id' => 1]);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function dispatch_passes_empty_payload_by_default(): void
    {
        Functions\expect('do_action')
            ->once()
            ->with('myvh_event_booking.confirmed', []);

        EventDispatcher::dispatch('booking.confirmed');
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function dispatch_prefixes_any_event_name(): void
    {
        Functions\expect('do_action')
            ->once()
            ->with('myvh_event_organisation.created', Mockery::any());

        EventDispatcher::dispatch('organisation.created', ['organisation_id' => 5]);
        $this->addToAssertionCount(1);
    }
}
