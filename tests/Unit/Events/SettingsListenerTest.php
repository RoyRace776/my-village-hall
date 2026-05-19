<?php

namespace MYVH\Tests\Unit\Events;

use Brain\Monkey\Functions;
use Mockery;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use MYVH\Container\Container;
use MYVH\Core\Scheduling\OvernightBatchRunner;
use MYVH\Events\SettingsEvents;
use MYVH\Events\SettingsListener;
use MYVH\Tests\Unit\UnitTestCase;
use Psr\Log\LoggerInterface;

class SettingsListenerTest extends UnitTestCase
{
    protected function tearDown(): void
    {
        global $myvh_container;
        $myvh_container = null;

        parent::tearDown();
    }

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

    /** @test */
    public function handle_invoicing_settings_saved_schedules_batch_when_enabled(): void
    {
        Functions\when('myvh_setting')->justReturn(true);

        Functions\expect('wp_next_scheduled')
            ->with(OvernightBatchRunner::HOOK)
            ->once()
            ->andReturn(false);

        Functions\expect('wp_timezone')
            ->once()
            ->andReturn(new \DateTimeZone('UTC'));

        Functions\expect('wp_schedule_event')
            ->with(Mockery::type('int'), 'daily', OvernightBatchRunner::HOOK)
            ->once();

        (new SettingsListener())->handle_invoicing_settings_saved([]);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function handle_invoicing_settings_saved_clears_batch_when_disabled(): void
    {
        Functions\expect('wp_clear_scheduled_hook')
            ->with(OvernightBatchRunner::HOOK)
            ->once();

        (new SettingsListener())->handle_invoicing_settings_saved([]);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function handle_admin_settings_saved_updates_logger_level_from_payload(): void
    {
        $handler = new TestHandler(Level::Info);
        $logger = new Logger('myvh-test');
        $logger->pushHandler($handler);

        $container = new Container();
        $container->singleton(LoggerInterface::class, static fn() => $logger);

        global $myvh_container;
        $myvh_container = $container;

        (new SettingsListener())->handle_admin_settings_saved([
            'settings' => [
                'logger_level' => 'error',
            ],
        ]);

        $this->assertSame(Level::Error, $handler->getLevel());
    }

    /** @test */
    public function handle_admin_settings_saved_defaults_to_emergency_when_missing(): void
    {
        $handler = new TestHandler(Level::Info);
        $logger = new Logger('myvh-test');
        $logger->pushHandler($handler);

        $container = new Container();
        $container->singleton(LoggerInterface::class, static fn() => $logger);

        global $myvh_container;
        $myvh_container = $container;

        (new SettingsListener())->handle_admin_settings_saved([
            'settings' => [],
        ]);

        $this->assertSame(Level::Emergency, $handler->getLevel());
    }
}