<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class RecurringBookingCreatorTest extends UnitTestCase {
    private $recurring_pattern_service;
    private $service;

    protected function setUp(): void {
        parent::setUp();

        $this->recurring_pattern_service = $this->mock(\MYVH\Bookings\RecurringPatternService::class);
        $this->service = new \MYVH\Bookings\Services\RecurringBookingCreator($this->recurring_pattern_service);
    }

    /** @test */
    public function create_for_booking_returns_empty_when_not_recurring(): void {
        $result = $this->service->create_for_booking(10, ['is_recurring' => 0]);

        $this->assertSame([], $result);
    }

    /** @test */
    public function create_for_booking_propagates_pattern_save_error(): void {
        $this->recurring_pattern_service->shouldReceive('save')
            ->once()
            ->andReturn(new WP_Error('pattern', 'Could not create pattern'));

        $result = $this->service->create_for_booking(10, [
            'is_recurring' => 1,
            'start_date' => '2026-06-01',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pattern', $result->get_error_code());
    }

    /** @test */
    public function create_for_booking_returns_conflict_and_error_warnings(): void {
        $this->recurring_pattern_service->shouldReceive('save')->once()->andReturn(201);
        $this->recurring_pattern_service->shouldReceive('get_last_booking_results')
            ->once()
            ->andReturn([
                'conflicts' => ['2026-06-08'],
                'errors' => ['Could not create 2026-06-15'],
            ]);

        $result = $this->service->create_for_booking(10, [
            'is_recurring' => 1,
            'recurrence_type' => 'weekly',
            'recurrence_interval' => 1,
            'start_date' => '2026-06-01',
            'recurrence_end_type' => 'date',
            'recurrence_end_date' => '2026-08-01',
        ]);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }
}
