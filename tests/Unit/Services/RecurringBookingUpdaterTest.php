<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class RecurringBookingUpdaterTest extends UnitTestCase {
    private $booking_repo;
    private $recurring_pattern_service;
    private $service;

    protected function setUp(): void {
        parent::setUp();

        $this->booking_repo = $this->mock(\MYVH\Bookings\BookingRepository::class);
        $this->recurring_pattern_service = $this->mock(\MYVH\Bookings\RecurringPatternService::class);

        $this->service = new \MYVH\Bookings\Services\RecurringBookingUpdater(
            $this->booking_repo,
            $this->recurring_pattern_service
        );
    }

    private function recurring_booking(int $id, array $overrides = []): array {
        return array_merge([
            'Id' => $id,
            'RecurringPatternId' => 200,
            'CustomerId' => 1,
            'OrganisationId' => 0,
            'RoomId' => 5,
            'Status' => 'confirmed',
            'StartDate' => '2026-06-01',
            'EndDate' => '2026-06-01',
            'StartTime' => '09:00:00',
            'EndTime' => '11:00:00',
            'Description' => 'Yoga',
            'Public' => 0,
            'NoInvoiceRequired' => 0,
        ], $overrides);
    }

    /** @test */
    public function update_with_scope_all_bookings_applies_updates_to_entire_series(): void {
        $current = $this->recurring_booking(99);
        $bookings = [
            $this->recurring_booking(99),
            $this->recurring_booking(100, ['StartDate' => '2026-06-08', 'EndDate' => '2026-06-08']),
        ];

        $data = [
            'booking_id' => 99,
            'description' => 'Updated',
            'status' => 'confirmed',
            'edit_scope' => 'all_bookings',
        ];

        $record = [
            'Status' => 'confirmed',
            'Description' => 'Updated',
            'Public' => 0,
            'NoInvoiceRequired' => 0,
        ];

        $this->booking_repo->shouldReceive('get_by_pattern_id')->with(200)->once()->andReturn($bookings);

        $call_count = 0;
        $result = $this->service->update_with_scope(
            $data,
            $record,
            $current,
            200,
            'all_bookings',
            function (int $booking_id, array $scoped_data, array $scoped_record, array $current_record) use (&$call_count): int {
                $call_count++;
                return $booking_id;
            }
        );

        $this->assertSame(99, $result);
        $this->assertSame(2, $call_count);
    }

    /** @test */
    public function update_with_scope_this_and_future_splits_series_and_applies_new_pattern_id(): void {
        $current = $this->recurring_booking(100, ['StartDate' => '2026-06-08', 'EndDate' => '2026-06-08']);
        $all_bookings = [
            $this->recurring_booking(99),
            $current,
            $this->recurring_booking(101, ['StartDate' => '2026-06-15', 'EndDate' => '2026-06-15']),
        ];
        $future_bookings = [
            $this->recurring_booking(100, ['RecurringPatternId' => 201, 'StartDate' => '2026-06-08', 'EndDate' => '2026-06-08']),
            $this->recurring_booking(101, ['RecurringPatternId' => 201, 'StartDate' => '2026-06-15', 'EndDate' => '2026-06-15']),
        ];

        $data = [
            'booking_id' => 100,
            'description' => 'Updated future',
            'status' => 'confirmed',
            'edit_scope' => 'this_and_future',
        ];

        $record = [
            'Status' => 'confirmed',
            'Description' => 'Updated future',
            'Public' => 0,
            'NoInvoiceRequired' => 0,
        ];

        $this->booking_repo->shouldReceive('get_by_pattern_id')->with(200)->once()->andReturn($all_bookings);
        $this->recurring_pattern_service->shouldReceive('split_pattern_from_booking')
            ->with(200, 100)
            ->once()
            ->andReturn([
                'new_pattern_id' => 201,
                'future_bookings' => $future_bookings,
            ]);

        $result = $this->service->update_with_scope(
            $data,
            $record,
            $current,
            200,
            'this_and_future',
            function (int $booking_id, array $scoped_data, array $scoped_record, array $current_record): int {
                return \intval($current_record['RecurringPatternId'] ?? 0);
            }
        );

        $this->assertSame(100, $result);
    }

    /** @test */
    public function update_with_scope_this_and_future_keeps_single_future_booking_standalone_when_split_collapses(): void {
        $current = $this->recurring_booking(100, ['StartDate' => '2026-06-08', 'EndDate' => '2026-06-08']);
        $all_bookings = [
            $this->recurring_booking(99),
            $current,
        ];
        $future_bookings = [
            $this->recurring_booking(100, ['RecurringPatternId' => null, 'StartDate' => '2026-06-08', 'EndDate' => '2026-06-08']),
        ];

        $data = [
            'booking_id' => 100,
            'description' => 'Updated standalone future',
            'status' => 'confirmed',
            'edit_scope' => 'this_and_future',
        ];

        $record = [
            'Status' => 'confirmed',
            'Description' => 'Updated standalone future',
            'Public' => 0,
            'NoInvoiceRequired' => 0,
        ];

        $this->booking_repo->shouldReceive('get_by_pattern_id')->with(200)->once()->andReturn($all_bookings);
        $this->recurring_pattern_service->shouldReceive('split_pattern_from_booking')
            ->with(200, 100)
            ->once()
            ->andReturn([
                'new_pattern_id' => 0,
                'future_bookings' => $future_bookings,
            ]);

        $seen_standalone = false;
        $result = $this->service->update_with_scope(
            $data,
            $record,
            $current,
            200,
            'this_and_future',
            function (int $booking_id, array $scoped_data, array $scoped_record, array $current_record) use (&$seen_standalone): int {
                $seen_standalone = !array_key_exists('RecurringPatternId', $scoped_record)
                    && empty($current_record['RecurringPatternId']);

                return $booking_id;
            }
        );

        $this->assertSame(100, $result);
        $this->assertTrue($seen_standalone);
    }

    /** @test */
    public function update_with_scope_rejects_schedule_changes_for_series_update(): void {
        $current = $this->recurring_booking(99, ['StartTime' => '09:00:00']);
        $data = [
            'booking_id' => 99,
            'start_time' => '10:00:00',
            'edit_scope' => 'all_bookings',
        ];

        $record = [
            'Status' => 'confirmed',
            'Description' => 'Updated',
            'Public' => 0,
            'NoInvoiceRequired' => 0,
        ];

        $result = $this->service->update_with_scope(
            $data,
            $record,
            $current,
            200,
            'all_bookings',
            static fn (): int => 99
        );

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function update_with_scope_returns_not_found_when_series_has_no_bookings(): void {
        $current = $this->recurring_booking(99);
        $data = [
            'booking_id' => 99,
            'edit_scope' => 'all_bookings',
        ];

        $record = [
            'Status' => 'confirmed',
            'Description' => 'Updated',
            'Public' => 0,
            'NoInvoiceRequired' => 0,
        ];

        $this->booking_repo->shouldReceive('get_by_pattern_id')->with(200)->once()->andReturn([]);

        $result = $this->service->update_with_scope(
            $data,
            $record,
            $current,
            200,
            'all_bookings',
            static fn (): int => 99
        );

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }
}
