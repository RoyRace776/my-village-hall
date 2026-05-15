<?php

namespace MYVH\Tests\Unit\Services;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class RecurringPatternServiceTest extends UnitTestCase {
    private $repo;
    private $booking_repo;
    private $service;

    protected function setUp(): void {
        parent::setUp();

        Functions\when('__')->alias(static fn(string $text): string => $text);
        Functions\when('sanitize_text_field')->alias(static fn($value): string => (string) $value);

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
    public function get_all_delegates_to_repository(): void {
        $expected = [['Id' => 1]];

        $this->repo->shouldReceive('get_all')
            ->once()
            ->with(['status' => 'active'])
            ->andReturn($expected);

        $actual = $this->service->get_all(['status' => 'active']);

        $this->assertSame($expected, $actual);
    }

    /** @test */
    public function get_and_get_by_id_delegate_to_repository(): void {
        $record = ['Id' => 22, 'RecurrenceType' => 'weekly'];

        $this->repo->shouldReceive('get_by_id')->once()->with(22)->andReturnUsing(static fn() => $record);
        $this->repo->shouldReceive('get_by_id')->once()->with(23)->andReturn(null);

        $this->assertSame($record, $this->service->get_by_id(22));
        $this->assertNull($this->service->get(23));
    }

    /** @test */
    public function get_active_with_bookings_delegates_to_repository(): void {
        $rows = [['Id' => 77], ['Id' => 78]];

        $this->repo->shouldReceive('get_all_active_with_bookings')
            ->once()
            ->andReturn($rows);

        $this->assertSame($rows, $this->service->get_active_with_bookings());
    }

    /** @test */
    public function get_by_parent_booking_delegates_to_repository(): void {
        $row = ['Id' => 33, 'ParentBookingId' => 91];

        $this->repo->shouldReceive('get_by_parent_booking')
            ->once()
            ->with(91)
            ->andReturnUsing(static fn() => $row);

        $this->assertSame($row, $this->service->get_by_parent_booking(91));
    }

    /** @test */
    public function update_pattern_delegates_update_with_id_where_clause(): void {
        $payload = ['IsActive' => 0, 'EndDate' => '2026-06-30'];

        $this->repo->shouldReceive('update')
            ->once()
            ->with($payload, ['Id' => 44])
            ->andReturn(true);

        $this->assertTrue($this->service->update_pattern(44, $payload));
    }

    /** @test */
    public function get_bookings_for_pattern_uses_booking_repository(): void {
        $bookings = [['Id' => 100], ['Id' => 101]];

        $this->booking_repo->shouldReceive('get_by_pattern_id')
            ->once()
            ->with(88)
            ->andReturn($bookings);

        $this->assertSame($bookings, $this->service->get_bookings_for_pattern(88));
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
                return \intval($record['ParentBookingId'] ?? 0) === 100
                    && \intval($record['OccurrenceCount'] ?? 0) === 2;
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
        $this->assertSame(201, \intval($result['new_pattern_id'] ?? 0));
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
                return \intval($record['ParentBookingId'] ?? 0) === 100
                    && \intval($record['OccurrenceCount'] ?? 0) === 1;
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
        $this->assertSame(0, \intval($result['new_pattern_id'] ?? -1));
        $this->assertFalse(isset($result['future_bookings'][0]['RecurringPatternId']));
        $this->assertSame(2, count($result['previous_bookings'] ?? []));
    }

    /** @test */
    public function split_pattern_from_booking_returns_not_found_when_pattern_missing(): void {
        $this->repo->shouldReceive('get_by_id')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->split_pattern_from_booking(999, 100);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }

    /** @test */
    public function split_pattern_from_booking_returns_not_found_when_selected_booking_is_not_in_pattern(): void {
        $pattern_id = 200;

        $this->repo->shouldReceive('get_by_id')
            ->once()
            ->with($pattern_id)
            ->andReturnUsing(static fn() => [
                'Id' => $pattern_id,
                'RecurrenceType' => 'weekly',
                'RecurrenceInterval' => 1,
                'RecurrenceDay' => 'monday',
                'RecurrenceWeek' => '',
                'EndDate' => '2026-12-31',
                'MaxOccurrences' => null,
                'OccurrenceCount' => 2,
                'IsActive' => 1,
            ]);

        $this->booking_repo->shouldReceive('get_by_pattern_id')
            ->once()
            ->with($pattern_id)
            ->andReturnUsing(fn() => [
                $this->recurring_booking(99, '2026-06-01'),
                $this->recurring_booking(101, '2026-06-15'),
            ]);

        $result = $this->service->split_pattern_from_booking($pattern_id, 100);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }

    /** @test */
    public function save_requires_parent_booking_id(): void {
        $result = $this->service->save([
            'recurrence_type' => 'weekly',
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-01',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function save_requires_recurrence_type(): void {
        $result = $this->service->save([
            'parent_booking_id' => 10,
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-01',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function save_rejects_invalid_recurrence_type(): void {
        $result = $this->service->save([
            'parent_booking_id' => 10,
            'recurrence_type' => 'hourly',
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-01',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function save_monthly_day_requires_week_and_day_values(): void {
        $result = $this->service->save([
            'parent_booking_id' => 10,
            'recurrence_type' => 'monthly_day',
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-01',
            'recurrence_week' => '',
            'recurrence_day' => '',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function save_monthly_day_rejects_invalid_week_or_day(): void {
        $result = $this->service->save([
            'parent_booking_id' => 10,
            'recurrence_type' => 'monthly_day',
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-01',
            'recurrence_week' => '6',
            'recurrence_day' => 'funday',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function save_requires_end_date_or_max_occurrences(): void {
        $result = $this->service->save([
            'parent_booking_id' => 10,
            'recurrence_type' => 'weekly',
            'start_date' => '2026-06-01',
            'end_date' => '',
            'max_occurrences' => '',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function save_rejects_max_occurrences_over_limit(): void {
        $result = $this->service->save([
            'parent_booking_id' => 10,
            'recurrence_type' => 'weekly',
            'start_date' => '2026-06-01',
            'max_occurrences' => 366,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function create_recurring_bookings_returns_not_found_when_pattern_does_not_exist(): void {
        $this->repo->shouldReceive('get_by_id')
            ->once()
            ->with(123)
            ->andReturn(null);

        $result = $this->service->create_recurring_bookings(123);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }

    /** @test */
    public function get_last_booking_results_is_null_before_any_save(): void {
        $this->assertNull($this->service->get_last_booking_results());
    }
}
