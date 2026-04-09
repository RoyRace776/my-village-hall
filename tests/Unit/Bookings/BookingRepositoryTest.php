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
use MYVH\Bookings\BookingRepository;
use MYVH\Tests\Unit\UnitTestCase;

class BookingRepositoryTest extends UnitTestCase
{
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