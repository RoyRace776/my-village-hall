<?php

namespace {
    if (!class_exists('wpdb')) {
        class wpdb {
            public $prefix = 'wp_';

            public function __construct($dbuser = '', $dbpassword = '', $dbname = '', $dbhost = '')
            {
            }
        }
    }
}

namespace MYVH\Tests\Unit\Bookings {

use Brain\Monkey\Functions;
use MYVH\Bookings\Booking;
use MYVH\Bookings\BookingRepository;
use MYVH\Bookings\CachedBookingRepository;
use MYVH\Tests\Unit\UnitTestCase;
use Tests\Support\Factories\BookingFactory;

class CachedBookingRepositoryTest extends UnitTestCase
{
    private array $cache = [];

    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('wp_cache_get')->alias(function ($key, $group = '') {
            return $this->cache[$group][$key] ?? false;
        });

        Functions\when('wp_cache_set')->alias(function ($key, $value, $group = '', $ttl = 0) {
            if (!isset($this->cache[$group])) {
                $this->cache[$group] = [];
            }

            $this->cache[$group][$key] = $value;

            return true;
        });
    }

    public function test_get_returns_cached_booking_after_first_lookup(): void
    {
        $repository = $this->makeRepositoryMock();
        $booking = $this->makeBooking(15);
        $repository->get_return = $booking;

        $cached_repository = new CachedBookingRepository($repository);

        $first = $cached_repository->get(15);
        $second = $cached_repository->get(15);

        $this->assertSame($booking, $first);
        $this->assertSame($booking, $second);
        $this->assertSame([15], $repository->get_calls);
    }

    public function test_get_all_cache_is_busted_after_create_increments_version(): void
    {
        $repository = $this->makeRepositoryMock();
        $repository->get_all_return_queue = [
            [$this->makeBooking(1)],
            [$this->makeBooking(2)],
        ];
        $repository->create_return = 99;

        $cached_repository = new CachedBookingRepository($repository);

        $before_write_first = $cached_repository->getAll();
        $before_write_second = $cached_repository->getAll();
        $result = $cached_repository->create(['CustomerId' => 10]);
        $after_write = $cached_repository->getAll();

        $this->assertSame(99, $result);
        $this->assertCount(1, $before_write_first);
        $this->assertSame(1, $before_write_first[0]->id());
        $this->assertSame(1, $before_write_second[0]->id());
        $this->assertCount(1, $after_write);
        $this->assertSame(2, $after_write[0]->id());
        $this->assertSame(2, $this->cache['myvh_bookings']['version']);
        $this->assertCount(2, $repository->get_all_calls);
        $this->assertSame([['CustomerId' => 10]], $repository->create_calls);
    }

    public function test_get_between_returns_cached_results_for_same_arguments(): void
    {
        $repository = $this->makeRepositoryMock();
        $bookings = [$this->makeBooking(31)];
        $repository->get_between_return = $bookings;

        $cached_repository = new CachedBookingRepository($repository);

        $first = $cached_repository->get_between('2026-06-01', '2026-06-30', 'public', ['room_id' => 4, 'statuses' => ['confirmed']]);
        $second = $cached_repository->get_between('2026-06-01', '2026-06-30', 'public', ['room_id' => 4, 'statuses' => ['confirmed']]);

        $this->assertSame($bookings, $first);
        $this->assertSame($bookings, $second);
        $this->assertSame([
            ['2026-06-01', '2026-06-30', 'public', ['room_id' => 4, 'statuses' => ['confirmed']]],
        ], $repository->get_between_calls);
    }

    public function test_get_all_with_details_returns_cached_results_for_same_arguments(): void
    {
        $repository = $this->makeRepositoryMock();
        $rows = [
            ['Id' => 5, 'Status' => 'confirmed', 'RoomName' => 'Main Hall'],
        ];
        $repository->get_all_with_details_return = $rows;

        $cached_repository = new CachedBookingRepository($repository);

        $first = $cached_repository->get_all_with_details(['status' => 'confirmed', 'room_id' => 7]);
        $second = $cached_repository->get_all_with_details(['status' => 'confirmed', 'room_id' => 7]);

        $this->assertSame($rows, $first);
        $this->assertSame($rows, $second);
        $this->assertSame([
            ['status' => 'confirmed', 'room_id' => 7],
        ], $repository->get_all_with_details_calls);
    }

    public function test_get_upcoming_bookings_returns_cached_results_for_same_customer(): void
    {
        $repository = $this->makeRepositoryMock();
        $rows = [
            ['Id' => 71, 'RoomName' => 'Committee Room'],
        ];
        $repository->get_upcoming_bookings_return = $rows;

        $cached_repository = new CachedBookingRepository($repository);

        $first = $cached_repository->get_upcoming_bookings(88);
        $second = $cached_repository->get_upcoming_bookings(88);

        $this->assertSame($rows, $first);
        $this->assertSame($rows, $second);
        $this->assertSame([88], $repository->get_upcoming_bookings_calls);
    }

    public function test_get_conflicts_does_not_use_cache(): void
    {
        $cache_get_calls = 0;
        $cache_set_calls = 0;

        Functions\when('wp_cache_get')->alias(function ($key, $group = '') use (&$cache_get_calls) {
            $cache_get_calls++;

            return $this->cache[$group][$key] ?? false;
        });

        Functions\when('wp_cache_set')->alias(function ($key, $value, $group = '', $ttl = 0) use (&$cache_set_calls) {
            $cache_set_calls++;

            if (!isset($this->cache[$group])) {
                $this->cache[$group] = [];
            }

            $this->cache[$group][$key] = $value;

            return true;
        });

        $repository = $this->makeRepositoryMock();
        $repository->get_conflicts_return_queue = [[['Id' => 12]], [['Id' => 13]]];

        $cached_repository = new CachedBookingRepository($repository);

        $first = $cached_repository->get_conflicts(4, '2026-06-01 09:00:00', '2026-06-01 10:00:00');
        $second = $cached_repository->get_conflicts(4, '2026-06-01 09:00:00', '2026-06-01 10:00:00');

        $this->assertSame([['Id' => 12]], $first);
        $this->assertSame([['Id' => 13]], $second);
        $this->assertSame(0, $cache_get_calls);
        $this->assertSame(0, $cache_set_calls);
        $this->assertSame([
            [4, '2026-06-01 09:00:00', '2026-06-01 10:00:00'],
            [4, '2026-06-01 09:00:00', '2026-06-01 10:00:00'],
        ], $repository->get_conflicts_calls);
    }

    private function makeRepositoryMock(): BookingRepositoryDouble
    {
        return new BookingRepositoryDouble(new \wpdb('', '', '', ''));
    }

    private function makeBooking(int $id): Booking
    {
        return BookingFactory::make([
            'Id' => $id,
            'CustomerId' => 20,
            'RoomId' => 30,
            'Start' => '2026-06-01 09:00:00',
            'End' => '2026-06-01 10:00:00',
        ]);
    }
}

class BookingRepositoryDouble extends BookingRepository
{
    public Booking $get_return;
    public array $get_calls = [];
    public array $get_all_return_queue = [];
    public array $get_all_calls = [];
    public array $get_between_calls = [];
    public array $get_between_return = [];
    public array $get_all_with_details_calls = [];
    public array $get_all_with_details_return = [];
    public array $get_upcoming_bookings_calls = [];
    public array $get_upcoming_bookings_return = [];
    public int|false $create_return = false;
    public array $create_calls = [];
    public array $get_conflicts_calls = [];
    public array $get_conflicts_return_queue = [];

    public function get(int $id): Booking
    {
        $this->get_calls[] = $id;

        return $this->get_return;
    }

    public function getAll(): array
    {
        $this->get_all_calls[] = true;

        return array_shift($this->get_all_return_queue);
    }

    public function get_between($start, $end, $context = null, $filters = []): array
    {
        $this->get_between_calls[] = [$start, $end, $context, $filters];

        return $this->get_between_return;
    }

    public function get_all_with_details($args = []): array
    {
        $this->get_all_with_details_calls[] = $args;

        return $this->get_all_with_details_return;
    }

    public function get_upcoming_bookings($customer_id): array
    {
        $this->get_upcoming_bookings_calls[] = $customer_id;

        return $this->get_upcoming_bookings_return;
    }

    public function create($data): int|false
    {
        $this->create_calls[] = $data;

        return $this->create_return;
    }

    public function get_conflicts($room_id, $start, $end): array
    {
        $this->get_conflicts_calls[] = [$room_id, $start, $end];

        return array_shift($this->get_conflicts_return_queue);
    }
}

}