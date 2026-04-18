<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Bookings\BookingStatus;
use MYVH\Tests\Unit\UnitTestCase;

class BookingListGroupingServiceTest extends UnitTestCase {
    private $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new \MYVH\Bookings\Services\BookingListGroupingService();
    }

    /** @test */
    public function group_bookings_builds_recurring_and_standalone_groups(): void {
        $groups = $this->service->group_bookings([
            [
                'Id' => 11,
                'RecurringPatternId' => 200,
                'RecurrenceType' => 'weekly',
                'RecurrenceInterval' => 1,
                'RecurrenceDay' => 'monday',
                'RecurrenceWeek' => '',
                'PatternStartDate' => '2026-05-01',
                'PatternEndDate' => '2026-07-01',
                'PatternIsActive' => 1,
                'StartDate' => '2026-05-05',
                'Status' => BookingStatus::PENDING->value,
            ],
            [
                'Id' => 12,
                'RecurringPatternId' => 200,
                'RecurrenceType' => 'weekly',
                'RecurrenceInterval' => 1,
                'RecurrenceDay' => 'monday',
                'RecurrenceWeek' => '',
                'PatternStartDate' => '2026-05-01',
                'PatternEndDate' => '2026-07-01',
                'PatternIsActive' => 1,
                'StartDate' => '2026-05-12',
                'Status' => BookingStatus::CONFIRMED->value,
            ],
            [
                'Id' => 99,
                'RecurringPatternId' => null,
                'StartDate' => '2026-05-06',
                'Status' => BookingStatus::PENDING->value,
            ],
        ]);

        $this->assertArrayHasKey('r_200', $groups);
        $this->assertArrayHasKey('b_99', $groups);
        $this->assertSame(BookingStatus::CONFIRMED->value, $groups['r_200']['status']);
        $this->assertSame(2, $groups['r_200']['count']);
    }
}
