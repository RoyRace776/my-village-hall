<?php

namespace {
    if (!class_exists('wpdb')) {
        class wpdb {
        }
    }

    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }
}

namespace MYVH\Tests\Unit\Bookings {

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Bookings\Booking;
use MYVH\Bookings\BookingRepository;
use MYVH\Bookings\BookingStatus;
use MYVH\Tests\Unit\UnitTestCase;

class BookingRepositoryTest extends UnitTestCase
{
    public function test_get_throws_when_booking_is_missing(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        /** @var BookingRepository&\Mockery\MockInterface $repository */
        $repository = Mockery::mock(BookingRepository::class, [$wpdb])
            ->makePartial();

        $repository->shouldReceive('get_by_id')
            ->once()
            ->with(404)
            ->andReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Booking 404 not found.');

        $repository->get(404);
    }

    public function test_get_hydrates_booking_entity_from_database_row(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        /** @var BookingRepository&\Mockery\MockInterface $repository */
        $repository = Mockery::mock(BookingRepository::class, [$wpdb])
            ->makePartial();

        $repository->shouldReceive('get_by_id')
            ->once()
            ->with(25)
            ->andReturn([
                'Id' => 25,
                'CustomerId' => 11,
                'RoomId' => 3,
                'OrganisationId' => 9,
                'Status' => BookingStatus::CONFIRMED->value,
                'StartDate' => '2026-06-01',
                'StartTime' => '09:00:00',
                'EndDate' => '2026-06-01',
                'EndTime' => '11:00:00',
                'AdminEmail' => 'admin@example.com',
            ]);

        $booking = $repository->get(25);

        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertSame(25, $booking->id());
        $this->assertSame(BookingStatus::CONFIRMED, $booking->status());
        $this->assertSame('2026-06-01 09:00:00', $booking->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-01 11:00:00', $booking->end()->format('Y-m-d H:i:s'));
        $this->assertSame('admin@example.com', $booking->adminEmail());
    }

    public function test_get_all_hydrates_each_booking_entity(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        /** @var BookingRepository&\Mockery\MockInterface $repository */
        $repository = Mockery::mock(BookingRepository::class, [$wpdb])
            ->makePartial();

        $repository->shouldReceive('get_all')
            ->once()
            ->andReturn([
                [
                    'Id' => 1,
                    'CustomerId' => 2,
                    'RoomId' => 3,
                    'OrganisationId' => 4,
                    'Status' => BookingStatus::PENDING->value,
                    'StartDate' => '2026-06-01',
                    'StartTime' => '09:00:00',
                    'EndDate' => '2026-06-01',
                    'EndTime' => '10:00:00',
                ],
                [
                    'Id' => 2,
                    'CustomerId' => 5,
                    'RoomId' => 6,
                    'OrganisationId' => 7,
                    'Status' => BookingStatus::COMPLETED->value,
                    'StartDate' => '2026-06-02',
                    'StartTime' => '11:00:00',
                    'EndDate' => '2026-06-02',
                    'EndTime' => '12:30:00',
                ],
            ]);

        $bookings = $repository->getAll();

        $this->assertCount(2, $bookings);
        $this->assertContainsOnlyInstancesOf(Booking::class, $bookings);
        $this->assertSame(BookingStatus::PENDING, $bookings[0]->status());
        $this->assertSame(BookingStatus::COMPLETED, $bookings[1]->status());
    }

    public function test_get_uninvoiced_bookings_allows_cancelled_invoice_links(): void
    {
        Functions\when('esc_sql')->alias(static fn($value) => $value);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';

        $capturedSql = null;

        $wpdb->shouldReceive('get_results')
            ->once()
            ->with(Mockery::on(function ($sql) use (&$capturedSql) {
                $capturedSql = $sql;
                return true;
            }), ARRAY_A)
            ->andReturn([]);

        $repository = new BookingRepository($wpdb);

        $repository->get_uninvoiced_bookings();

        $this->assertNotNull($capturedSql);
        $sql = (string) $capturedSql;

        $this->assertStringNotContainsString('ii.Id IS NULL', $sql);
        $this->assertStringContainsString("LEFT JOIN wp_myvh_invoice_items ii ON b.Id = ii.BookingId", $sql);
        $this->assertStringContainsString("AND i.Status NOT IN ('cancelled')", $sql);
        $this->assertStringContainsString('HAVING COUNT(i.Id) = 0', $sql);
    }

    public function test_get_by_pattern_id_returns_results_from_database(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), 55)
            ->andReturn('prepared-sql');

        $wpdb->shouldReceive('get_results')
            ->once()
            ->with('prepared-sql', ARRAY_A)
            ->andReturn([
                ['Id' => 11, 'RecurringPatternId' => 55],
            ]);

        $repository = new BookingRepository($wpdb);

        $rows = $repository->get_by_pattern_id(55);

        $this->assertCount(1, $rows);
        $this->assertSame(11, \intval($rows[0]['Id']));
    }

    public function test_cancel_future_by_pattern_executes_update_query(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), BookingStatus::CANCELLED->value, 9, Mockery::type('string'), BookingStatus::CANCELLED->value)
            ->andReturn('prepared-cancel-sql');

        $wpdb->shouldReceive('query')
            ->once()
            ->with('prepared-cancel-sql')
            ->andReturn(3);

        $repository = new BookingRepository($wpdb);

        $result = $repository->cancel_future_by_pattern(9);

        $this->assertSame(3, $result);
    }

    public function test_delete_future_by_pattern_executes_delete_query(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), 9, Mockery::type('string'))
            ->andReturn('prepared-delete-sql');

        $wpdb->shouldReceive('query')
            ->once()
            ->with('prepared-delete-sql')
            ->andReturn(2);

        $repository = new BookingRepository($wpdb);

        $result = $repository->delete_future_by_pattern(9);

        $this->assertSame(2, $result);
    }

    public function test_has_conflict_returns_true_for_invalid_time_range(): void
    {
        Functions\when('sanitize_text_field')->alias(static fn($value) => (string) $value);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $repository = new BookingRepository($wpdb);

        $has_conflict = $repository->has_conflict(4, '2026-06-01', '12:00', '11:00');

        $this->assertTrue($has_conflict);
    }

    public function test_has_conflict_appends_excluded_booking_filter_when_provided(): void
    {
        Functions\when('sanitize_text_field')->alias(static fn($value) => (string) $value);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), 7, BookingStatus::CANCELLED->value, '2026-06-01 13:00:00', '2026-06-01 09:00:00')
            ->andReturn('base-sql');

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(' AND Id != %d', 88)
            ->andReturn(' and-id-filter');

        $wpdb->shouldReceive('get_var')
            ->once()
            ->with('base-sql and-id-filter')
            ->andReturn(1);

        $repository = new BookingRepository($wpdb);

        $has_conflict = $repository->has_conflict(7, '2026-06-01', '09:00', '13:00', 88);

        $this->assertTrue($has_conflict);
    }

    public function test_count_by_customer_returns_integer_value(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), 7)
            ->andReturn('prepared-count-sql');

        $wpdb->shouldReceive('get_var')
            ->once()
            ->with('prepared-count-sql')
            ->andReturn('3');

        $repository = new BookingRepository($wpdb);

        $this->assertSame(3, $repository->count_by_customer(7));
    }

    public function test_get_conflicts_returns_empty_when_datetimes_are_invalid(): void
    {
        Functions\when('sanitize_text_field')->alias(static fn($value) => (string) $value);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $repository = new BookingRepository($wpdb);

        $rows = $repository->get_conflicts(5, '', '');

        $this->assertSame([], $rows);
    }

    public function test_get_conflicts_returns_matching_rows_for_valid_window(): void
    {
        Functions\when('sanitize_text_field')->alias(static fn($value) => (string) $value);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), 5, BookingStatus::CANCELLED->value, '2026-06-01 11:00:00', '2026-06-01 09:00:00')
            ->andReturn('prepared-conflicts-sql');

        $wpdb->shouldReceive('get_results')
            ->once()
            ->with('prepared-conflicts-sql', ARRAY_A)
            ->andReturnUsing(static fn() => [['Id' => 44]]);

        $repository = new BookingRepository($wpdb);

        $rows = $repository->get_conflicts(5, '2026-06-01 09:00:00', '2026-06-01 11:00:00');

        $this->assertCount(1, $rows);
        $this->assertSame(44, \intval($rows[0]['Id']));
    }

    public function test_get_between_hydrates_booking_rows_with_filters(): void
    {
        Functions\when('sanitize_text_field')->alias(static fn($value) => (string) $value);
        Functions\when('wp_parse_args')->alias(static fn($args, $defaults) => array_merge($defaults, is_array($args) ? $args : []));

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $wpdb->shouldReceive('prepare')
            ->once()
            ->withArgs(function ($sql, ...$params): bool {
                return is_string($sql)
                    && str_contains($sql, 'SELECT b.*, r.Name AS RoomName')
                    && $params === ['2026-06-30', '2026-06-01', 5, 2, 'confirmed'];
            })
            ->andReturn('prepared-between-sql');

        $wpdb->shouldReceive('get_results')
            ->once()
            ->with('prepared-between-sql', ARRAY_A)
            ->andReturnUsing(static fn() => [[
                'Id' => 81,
                'CustomerId' => 4,
                'RoomId' => 5,
                'OrganisationId' => 0,
                'Status' => BookingStatus::CONFIRMED->value,
                'StartDate' => '2026-06-10',
                'StartTime' => '10:00:00',
                'EndDate' => '2026-06-10',
                'EndTime' => '11:00:00',
            ]]);

        $repository = new BookingRepository($wpdb);

        $bookings = $repository->get_between('2026-06-01', '2026-06-30', null, [
            'room_id' => 5,
            'venue_id' => 2,
            'statuses' => ['confirmed'],
        ]);

        $this->assertCount(1, $bookings);
        $this->assertInstanceOf(Booking::class, $bookings[0]);
        $this->assertSame(81, $bookings[0]->id());
    }

    public function test_get_upcoming_bookings_returns_query_rows(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), Mockery::type('string'), 15)
            ->andReturn('prepared-upcoming-sql');

        $wpdb->shouldReceive('get_results')
            ->once()
            ->with('prepared-upcoming-sql', ARRAY_A)
            ->andReturnUsing(static fn() => [['Id' => 101]]);

        $repository = new BookingRepository($wpdb);

        $rows = $repository->get_upcoming_bookings(15);

        $this->assertCount(1, $rows);
        $this->assertSame(101, \intval($rows[0]['Id']));
    }

    public function test_has_invoiced_items_and_no_invoice_required_flags(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), 71)
            ->andReturn('prepared-has-invoices-sql');

        $wpdb->shouldReceive('get_var')
            ->once()
            ->with('prepared-has-invoices-sql')
            ->andReturn('1');

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), 71)
            ->andReturn('prepared-no-invoice-flag-sql');

        $wpdb->shouldReceive('get_var')
            ->once()
            ->with('prepared-no-invoice-flag-sql')
            ->andReturn('1');

        $repository = new BookingRepository($wpdb);

        $this->assertTrue($repository->has_invoiced_items(71));
        $this->assertTrue($repository->is_no_invoice_required(71));
    }

    public function test_get_active_invoice_ids_for_booking_casts_values_to_ints(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), 42)
            ->andReturn('prepared-active-invoice-sql');

        $wpdb->shouldReceive('get_col')
            ->once()
            ->with('prepared-active-invoice-sql')
            ->andReturnUsing(static fn() => ['9', '12']);

        $repository = new BookingRepository($wpdb);

        $invoiceIds = $repository->get_active_invoice_ids_for_booking(42);

        $this->assertSame([9, 12], $invoiceIds);
    }

    public function test_has_conflict_in_buffer_window_applies_optional_exclusion(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), 5, BookingStatus::CANCELLED->value, '2026-06-01 12:00:00', '2026-06-01 08:00:00')
            ->andReturn('buffer-base-sql');

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(' AND Id != %d', 33)
            ->andReturn(' and-id-filter');

        $wpdb->shouldReceive('get_var')
            ->once()
            ->with('buffer-base-sql and-id-filter')
            ->andReturn(1);

        $repository = new BookingRepository($wpdb);

        $this->assertTrue($repository->has_conflict_in_buffer_window(5, '2026-06-01 08:00:00', '2026-06-01 12:00:00', 33));
    }
}

}