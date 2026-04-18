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
}

}