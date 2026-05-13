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

    public function test_mutating_operations_increment_cache_version(): void
    {
        $repository = $this->makeRepositoryMock();
        $repository->create_return = 99;
        $repository->update_return = true;
        $repository->delete_return = true;
        $repository->cancel_future_by_pattern_return = 2;
        $repository->delete_future_by_pattern_return = 1;
        $repository->delete_by_id_return = true;
        $repository->soft_delete_by_id_return = true;
        $repository->restore_by_id_return = true;
        $repository->move_booking_return = 1;

        $cached_repository = new CachedBookingRepository($repository);

        $this->assertSame(99, $cached_repository->create(['CustomerId' => 1]));
        $this->assertTrue($cached_repository->update(['Status' => 'confirmed'], ['Id' => 9]));
        $this->assertTrue($cached_repository->delete(9));
        $this->assertSame(2, $cached_repository->cancel_future_by_pattern(7));
        $this->assertSame(1, $cached_repository->delete_future_by_pattern(7));
        $this->assertTrue($cached_repository->delete_by_id(10));
        $this->assertTrue($cached_repository->soft_delete_by_id(10));
        $this->assertTrue($cached_repository->restore_by_id(10));
        $this->assertSame(1, $cached_repository->move_booking(10, '2026-06-01T10:00:00', '2026-06-01T11:00:00', 4));

        $this->assertSame(10, $this->cache['myvh_bookings']['version']);
    }

    public function test_delegate_methods_return_repository_values(): void
    {
        $repository = $this->makeRepositoryMock();
        $repository->get_by_id_return = ['Id' => 12];
        $repository->get_all_return_value = [['Id' => 13]];
        $repository->find_return = [['Id' => 14]];
        $repository->with_deleted_return = [['Id' => 15]];
        $repository->only_deleted_return = [['Id' => 16]];
        $repository->paginate_return = ['items' => [['Id' => 17]], 'total' => 1];
        $repository->get_by_pattern_id_return = [['Id' => 18]];
        $repository->has_conflict_return = true;
        $repository->count_by_customer_return = 4;
        $repository->uninvoiced_bookings_return = [['Id' => 19]];
        $repository->uninvoiced_single_return = [['Id' => 20]];
        $repository->uninvoiced_recurring_return = [['Id' => 21]];
        $repository->count_uninvoiced_by_org_return = [['OrganisationId' => 2, 'UninvoicedCount' => 3]];
        $repository->count_uninvoiced_by_customer_return = [['CustomerId' => 8, 'UninvoicedCount' => 1]];
        $repository->has_invoiced_items_return = true;
        $repository->active_invoice_ids_return = [5, 6];
        $repository->is_no_invoice_required_return = true;
        $repository->has_conflict_in_buffer_window_return = true;

        $cached_repository = new CachedBookingRepository($repository);

        $cached_repository->begin();
        $cached_repository->commit();
        $cached_repository->rollback();

        $this->assertSame(['Id' => 12], $cached_repository->get_by_id(12));
        $this->assertSame([['Id' => 13]], $cached_repository->get_all(['status' => 'confirmed']));
        $this->assertSame([['Id' => 14]], $cached_repository->find(['Status' => 'confirmed'], 'Id DESC', 10, 0));
        $this->assertSame([['Id' => 15]], $cached_repository->with_deleted());
        $this->assertSame([['Id' => 16]], $cached_repository->only_deleted());
        $this->assertSame(['items' => [['Id' => 17]], 'total' => 1], $cached_repository->paginate(1, 20, ['Status' => 'confirmed']));
        $this->assertSame([['Id' => 18]], $cached_repository->get_by_pattern_id(3));
        $this->assertTrue($cached_repository->has_conflict(2, '2026-06-01', '09:00', '10:00'));
        $this->assertSame(4, $cached_repository->count_by_customer(12));
        $this->assertSame([['Id' => 19]], $cached_repository->get_uninvoiced_bookings(['organisation_id' => 2]));
        $this->assertSame([['Id' => 20]], $cached_repository->get_uninvoiced_single_bookings());
        $this->assertSame([['Id' => 21]], $cached_repository->get_uninvoiced_recurring_bookings());
        $this->assertSame([['OrganisationId' => 2, 'UninvoicedCount' => 3]], $cached_repository->count_uninvoiced_by_organisation());
        $this->assertSame([['CustomerId' => 8, 'UninvoicedCount' => 1]], $cached_repository->count_uninvoiced_by_customer());
        $this->assertTrue($cached_repository->has_invoiced_items(19));
        $this->assertSame([5, 6], $cached_repository->get_active_invoice_ids_for_booking(19));
        $this->assertTrue($cached_repository->is_no_invoice_required(19));
        $this->assertTrue($cached_repository->has_conflict_in_buffer_window(2, '2026-06-01 08:00:00', '2026-06-01 10:00:00'));

        $this->assertSame(1, $repository->begin_calls);
        $this->assertSame(1, $repository->commit_calls);
        $this->assertSame(1, $repository->rollback_calls);
    }

    public function test_magic_call_forwards_to_underlying_repository(): void
    {
        $repository = $this->makeRepositoryMock();
        $cached_repository = new CachedBookingRepository($repository);

        $result = $cached_repository->custom_helper('abc');

        $this->assertSame('custom:abc', $result);
        $this->assertSame([['abc']], $repository->custom_helper_calls);
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
    public bool $update_return = true;
    public array $update_calls = [];
    public bool $delete_return = true;
    public array $delete_calls = [];
    public int|false $cancel_future_by_pattern_return = 0;
    public array $cancel_future_by_pattern_calls = [];
    public int|false $delete_future_by_pattern_return = 0;
    public array $delete_future_by_pattern_calls = [];
    public int $begin_calls = 0;
    public int $commit_calls = 0;
    public int $rollback_calls = 0;
    public ?array $get_by_id_return = null;
    public array $get_by_id_calls = [];
    public array $get_all_return_value = [];
    public array $get_all_args_calls = [];
    public bool $delete_by_id_return = true;
    public array $delete_by_id_calls = [];
    public array $find_return = [];
    public array $find_calls = [];
    public bool $soft_delete_by_id_return = true;
    public array $soft_delete_by_id_calls = [];
    public bool $restore_by_id_return = true;
    public array $restore_by_id_calls = [];
    public array $with_deleted_return = [];
    public int $with_deleted_calls = 0;
    public array $only_deleted_return = [];
    public int $only_deleted_calls = 0;
    public array $paginate_return = [];
    public array $paginate_calls = [];
    public array $get_by_pattern_id_return = [];
    public array $get_by_pattern_id_calls = [];
    public bool $has_conflict_return = false;
    public array $has_conflict_calls = [];
    public int $count_by_customer_return = 0;
    public array $count_by_customer_calls = [];
    public array $uninvoiced_bookings_return = [];
    public array $uninvoiced_bookings_calls = [];
    public array $uninvoiced_single_return = [];
    public array $uninvoiced_single_calls = [];
    public array $uninvoiced_recurring_return = [];
    public array $uninvoiced_recurring_calls = [];
    public array $count_uninvoiced_by_org_return = [];
    public int $count_uninvoiced_by_org_calls = 0;
    public array $count_uninvoiced_by_customer_return = [];
    public int $count_uninvoiced_by_customer_calls = 0;
    public bool $has_invoiced_items_return = false;
    public array $has_invoiced_items_calls = [];
    public array $active_invoice_ids_return = [];
    public array $active_invoice_ids_calls = [];
    public bool $is_no_invoice_required_return = false;
    public array $is_no_invoice_required_calls = [];
    public bool $has_conflict_in_buffer_window_return = false;
    public array $has_conflict_in_buffer_window_calls = [];
    public int|\WP_Error $move_booking_return = 1;
    public array $move_booking_calls = [];
    public array $custom_helper_calls = [];

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

    public function update($data, $where): bool
    {
        $this->update_calls[] = [$data, $where];

        return $this->update_return;
    }

    public function delete($id): bool
    {
        $this->delete_calls[] = $id;

        return $this->delete_return;
    }

    public function cancel_future_by_pattern($pattern_id): int|false
    {
        $this->cancel_future_by_pattern_calls[] = $pattern_id;

        return $this->cancel_future_by_pattern_return;
    }

    public function delete_future_by_pattern($pattern_id): int|false
    {
        $this->delete_future_by_pattern_calls[] = $pattern_id;

        return $this->delete_future_by_pattern_return;
    }

    public function begin(): void
    {
        $this->begin_calls++;
    }

    public function commit(): void
    {
        $this->commit_calls++;
    }

    public function rollback(): void
    {
        $this->rollback_calls++;
    }

    public function get_by_id($id): ?array
    {
        $this->get_by_id_calls[] = $id;

        return $this->get_by_id_return;
    }

    public function get_all($args = []): array
    {
        $this->get_all_args_calls[] = $args;

        return $this->get_all_return_value;
    }

    public function delete_by_id($id): bool
    {
        $this->delete_by_id_calls[] = $id;

        return $this->delete_by_id_return;
    }

    public function find($where = [], $order = '', $limit = null, $offset = null): array
    {
        $this->find_calls[] = [$where, $order, $limit, $offset];

        return $this->find_return;
    }

    public function soft_delete_by_id($id): bool
    {
        $this->soft_delete_by_id_calls[] = $id;

        return $this->soft_delete_by_id_return;
    }

    public function restore_by_id($id): bool
    {
        $this->restore_by_id_calls[] = $id;

        return $this->restore_by_id_return;
    }

    public function with_deleted(): array
    {
        $this->with_deleted_calls++;

        return $this->with_deleted_return;
    }

    public function only_deleted(): array
    {
        $this->only_deleted_calls++;

        return $this->only_deleted_return;
    }

    public function paginate($page = 1, $per_page = 20, $where = []): array
    {
        $this->paginate_calls[] = [$page, $per_page, $where];

        return $this->paginate_return;
    }

    public function get_by_pattern_id($pattern_id): array
    {
        $this->get_by_pattern_id_calls[] = $pattern_id;

        return $this->get_by_pattern_id_return;
    }

    public function has_conflict($room_id, $date, $start_time, $end_time, $exclude_booking_id = null, $end_date = null): bool
    {
        $this->has_conflict_calls[] = [$room_id, $date, $start_time, $end_time, $exclude_booking_id, $end_date];

        return $this->has_conflict_return;
    }

    public function count_by_customer(int $customer_id): int
    {
        $this->count_by_customer_calls[] = $customer_id;

        return $this->count_by_customer_return;
    }

    public function get_conflicts($room_id, $start, $end): array
    {
        $this->get_conflicts_calls[] = [$room_id, $start, $end];

        return array_shift($this->get_conflicts_return_queue);
    }

    public function get_uninvoiced_bookings($args = []): array
    {
        $this->uninvoiced_bookings_calls[] = $args;

        return $this->uninvoiced_bookings_return;
    }

    public function get_uninvoiced_single_bookings($args = []): array
    {
        $this->uninvoiced_single_calls[] = $args;

        return $this->uninvoiced_single_return;
    }

    public function get_uninvoiced_recurring_bookings($args = []): array
    {
        $this->uninvoiced_recurring_calls[] = $args;

        return $this->uninvoiced_recurring_return;
    }

    public function count_uninvoiced_by_organisation(): array
    {
        $this->count_uninvoiced_by_org_calls++;

        return $this->count_uninvoiced_by_org_return;
    }

    public function count_uninvoiced_by_customer(): array
    {
        $this->count_uninvoiced_by_customer_calls++;

        return $this->count_uninvoiced_by_customer_return;
    }

    public function has_invoiced_items($booking_id): bool
    {
        $this->has_invoiced_items_calls[] = $booking_id;

        return $this->has_invoiced_items_return;
    }

    public function get_active_invoice_ids_for_booking(int $booking_id): array
    {
        $this->active_invoice_ids_calls[] = $booking_id;

        return $this->active_invoice_ids_return;
    }

    public function is_no_invoice_required(int $booking_id): bool
    {
        $this->is_no_invoice_required_calls[] = $booking_id;

        return $this->is_no_invoice_required_return;
    }

    public function has_conflict_in_buffer_window(int $room_id, string $window_start, string $window_end, ?int $exclude_booking_id = null): bool
    {
        $this->has_conflict_in_buffer_window_calls[] = [$room_id, $window_start, $window_end, $exclude_booking_id];

        return $this->has_conflict_in_buffer_window_return;
    }

    public function move_booking($id, $start, $end, $room): int|\WP_Error
    {
        $this->move_booking_calls[] = [$id, $start, $end, $room];

        return $this->move_booking_return;
    }

    public function custom_helper(string $value): string
    {
        $this->custom_helper_calls[] = [$value];

        return 'custom:' . $value;
    }
}

}