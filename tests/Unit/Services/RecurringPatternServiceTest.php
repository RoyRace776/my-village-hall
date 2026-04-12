<?php

namespace MYVH\Tests\Unit\Services;

use Mockery;
use MYVH\Tests\Unit\UnitTestCase;

class RecurringPatternServiceTest extends UnitTestCase {
    private $repo;
    private $booking_repo;
    private $service;

    protected function setUp(): void {
        parent::setUp();

        $this->repo = $this->mock(\MYVH\Bookings\RecurringPatternRepository::class);
        $this->booking_repo = $this->mock(\MYVH\Bookings\BookingRepository::class);

        $this->service = new \MYVH\Bookings\RecurringPatternService(
            $this->repo,
            $this->booking_repo
        );
    }

    private function recurring_booking(int $id, string $start_date): array {
        return [
            'Id' => $id,
            'RecurringPatternId' => 200,
            'StartDate' => $start_date,
            'EndDate' => $start_date,
            'StartTime' => '09:00:00',
            'EndTime' => '11:00:00',
        ];
    }

    /** @test */
    public function split_pattern_from_booking_collapses_previous_side_when_only_one_booking_remains(): void {
        $pattern_id = 200;
        $selected_booking_id = 100;

        $bookings = [
            $this->recurring_booking(99, '2026-06-01'),
            $this->recurring_booking(100, '2026-06-08'),
            $this->recurring_booking(101, '2026-06-15'),
        ];

        $this->repo->shouldReceive('get_by_id')
            ->with($pattern_id)
            ->once()
            ->andReturnUsing(static fn () => [
                'Id' => $pattern_id,
                'RecurrenceType' => 'weekly',
                'RecurrenceInterval' => 1,
                'RecurrenceDay' => 'monday',
                'RecurrenceWeek' => '',
                'EndDate' => '2026-12-31',
                'MaxOccurrences' => null,
                'OccurrenceCount' => 3,
                'IsActive' => 1,
            ]);

        $this->booking_repo->shouldReceive('get_by_pattern_id')->with($pattern_id)->once()->andReturn($bookings);

        $this->repo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(static function (array $record): bool {
                return intval($record['ParentBookingId'] ?? 0) === 100
                    && intval($record['OccurrenceCount'] ?? 0) === 2;
            }))
            ->andReturn(201);

        $this->booking_repo->shouldReceive('update')->once()->with(['RecurringPatternId' => 201], ['Id' => 100])->andReturn(true);
        $this->booking_repo->shouldReceive('update')->once()->with(['RecurringPatternId' => 201], ['Id' => 101])->andReturn(true);
        $this->booking_repo->shouldReceive('update')->once()->with(['RecurringPatternId' => null], ['Id' => 99])->andReturn(true);

        $this->repo->shouldReceive('update')
            ->once()
            ->with(['OccurrenceCount' => 1, 'EndDate' => '2026-06-01'], ['Id' => $pattern_id])
            ->andReturn(true);

        $this->repo->shouldReceive('update')
            ->once()
            ->with(['OccurrenceCount' => 0, 'IsActive' => 0], ['Id' => $pattern_id])
            ->andReturn(true);

        $result = $this->service->split_pattern_from_booking($pattern_id, $selected_booking_id);

        $this->assertIsArray($result);
        $this->assertSame(201, intval($result['new_pattern_id'] ?? 0));
        $this->assertFalse(isset($result['previous_bookings'][0]['RecurringPatternId']));
        $this->assertSame(2, count($result['future_bookings'] ?? []));
    }

    /** @test */
    public function split_pattern_from_booking_collapses_future_side_when_only_one_booking_remains(): void {
        $pattern_id = 200;
        $selected_booking_id = 100;

        $bookings = [
            $this->recurring_booking(98, '2026-05-25'),
            $this->recurring_booking(99, '2026-06-01'),
            $this->recurring_booking(100, '2026-06-08'),
        ];

        $this->repo->shouldReceive('get_by_id')
            ->with($pattern_id)
            ->once()
            ->andReturnUsing(static fn () => [
                'Id' => $pattern_id,
                'RecurrenceType' => 'weekly',
                'RecurrenceInterval' => 1,
                'RecurrenceDay' => 'monday',
                'RecurrenceWeek' => '',
                'EndDate' => '2026-12-31',
                'MaxOccurrences' => null,
                'OccurrenceCount' => 3,
                'IsActive' => 1,
            ]);

        $this->booking_repo->shouldReceive('get_by_pattern_id')->with($pattern_id)->once()->andReturn($bookings);

        $this->repo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(static function (array $record): bool {
                return intval($record['ParentBookingId'] ?? 0) === 100
                    && intval($record['OccurrenceCount'] ?? 0) === 1;
            }))
            ->andReturn(201);

        $this->booking_repo->shouldReceive('update')->once()->with(['RecurringPatternId' => 201], ['Id' => 100])->andReturn(true);
        $this->booking_repo->shouldReceive('update')->once()->with(['RecurringPatternId' => null], ['Id' => 100])->andReturn(true);

        $this->repo->shouldReceive('update')
            ->once()
            ->with(['OccurrenceCount' => 2, 'EndDate' => '2026-06-01'], ['Id' => $pattern_id])
            ->andReturn(true);

        $this->repo->shouldReceive('update')
            ->once()
            ->with(['OccurrenceCount' => 0, 'IsActive' => 0], ['Id' => 201])
            ->andReturn(true);

        $result = $this->service->split_pattern_from_booking($pattern_id, $selected_booking_id);

        $this->assertIsArray($result);
        $this->assertSame(0, intval($result['new_pattern_id'] ?? -1));
        $this->assertFalse(isset($result['future_bookings'][0]['RecurringPatternId']));
        $this->assertSame(2, count($result['previous_bookings'] ?? []));
    }
}
