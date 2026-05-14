<?php

namespace MYVH\Tests\Unit\Events;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Events\SettingsEvents;
use MYVH\Events\SettingsListener;
use MYVH\Tests\Unit\UnitTestCase;

class SettingsListenerTest extends UnitTestCase
{
    /** @test */
    public function register_hooks_all_settings_saved_events(): void
    {
        $expected_hooks = [
            'myvh_event_' . SettingsEvents::ADMIN_SAVED,
            'myvh_event_' . SettingsEvents::BOOKING_SAVED,
            'myvh_event_' . SettingsEvents::CALENDAR_SAVED,
            'myvh_event_' . SettingsEvents::GENERAL_SAVED,
            'myvh_event_' . SettingsEvents::INVOICING_SAVED,
            'myvh_event_' . SettingsEvents::NOTICES_SAVED,
        ];

        foreach ($expected_hooks as $hook) {
            Functions\expect('add_action')
                ->with($hook, Mockery::type('array'))
                ->once();
        }

        (new SettingsListener())->register();
        $this->addToAssertionCount(1);
    }
}