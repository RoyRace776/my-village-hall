<?php

namespace MYVH\Tests\Unit\Scheduling;

use Brain\Monkey\Functions;
use MYVH\Core\Scheduling\OvernightJobScheduler;
use MYVH\Tests\Unit\UnitTestCase;

class OvernightJobSchedulerTest extends UnitTestCase
{
    /** @test */
    public function compute_next_4am_returns_future_timestamp(): void
    {
        $tz = new \DateTimeZone('Europe/London');

        Functions\expect('wp_timezone')
            ->once()
            ->andReturn($tz);

        $result = OvernightJobScheduler::compute_next_4am();

        $this->assertGreaterThan(time(), $result, 'compute_next_4am should return a future timestamp');

        $local = new \DateTimeImmutable('@' . $result, $tz);
        $local = $local->setTimezone($tz);
        $this->assertEquals('04:00', $local->format('H:i'), '04:00 local time expected');
    }

    /** @test */
    public function schedule_registers_event_when_not_already_scheduled(): void
    {
        $tz = new \DateTimeZone('UTC');

        Functions\expect('wp_next_scheduled')
            ->once()
            ->with('myvh_overnight_batch')
            ->andReturn(false);

        Functions\expect('wp_timezone')
            ->once()
            ->andReturn($tz);

        Functions\expect('wp_schedule_event')
            ->once()
            ->with(\Mockery::type('int'), 'daily', 'myvh_overnight_batch');

        OvernightJobScheduler::schedule('myvh_overnight_batch');
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function schedule_is_no_op_when_already_scheduled(): void
    {
        Functions\expect('wp_next_scheduled')
            ->once()
            ->with('myvh_overnight_batch')
            ->andReturn(time() + 3600);

        Functions\expect('wp_schedule_event')->never();

        OvernightJobScheduler::schedule('myvh_overnight_batch');
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function clear_delegates_to_wp_clear_scheduled_hook(): void
    {
        Functions\expect('wp_clear_scheduled_hook')
            ->once()
            ->with('myvh_overnight_batch');

        OvernightJobScheduler::clear('myvh_overnight_batch');
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function is_scheduled_returns_true_when_hook_has_next_run(): void
    {
        Functions\expect('wp_next_scheduled')
            ->once()
            ->with('myvh_overnight_batch')
            ->andReturn(time() + 3600);

        $this->assertTrue(OvernightJobScheduler::is_scheduled('myvh_overnight_batch'));
    }

    /** @test */
    public function is_scheduled_returns_false_when_not_scheduled(): void
    {
        Functions\expect('wp_next_scheduled')
            ->once()
            ->with('myvh_overnight_batch')
            ->andReturn(false);

        $this->assertFalse(OvernightJobScheduler::is_scheduled('myvh_overnight_batch'));
    }
}
