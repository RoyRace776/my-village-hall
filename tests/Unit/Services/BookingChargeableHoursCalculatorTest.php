<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Tests\Unit\UnitTestCase;

class BookingChargeableHoursCalculatorTest extends UnitTestCase {
    private $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new \MYVH\Bookings\Services\BookingChargeableHoursCalculator();
    }

    /** @test */
    public function calculate_returns_raw_hours_when_calc_closed_hours_is_enabled(): void {
        $hours = $this->service->calculate(
            '2026-06-01',
            '09:00:00',
            '2026-06-01',
            '11:00:00',
            [
                'CalcClosedHours' => 1,
                'OpeningTime' => '08:00:00',
                'ClosingTime' => '22:00:00',
            ]
        );

        $this->assertSame(2.0, $hours);
    }

    /** @test */
    public function calculate_excludes_closed_hours_per_day_when_configured(): void {
        $hours = $this->service->calculate(
            '2026-06-01',
            '09:00:00',
            '2026-06-02',
            '09:00:00',
            [
                'CalcClosedHours' => 0,
                'OpeningTime' => '08:00:00',
                'ClosingTime' => '22:00:00',
            ]
        );

        $this->assertSame(14.0, $hours);
    }
}
