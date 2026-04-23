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

namespace MYVH\Tests\Unit\Rooms {

use Brain\Monkey\Functions;
use MYVH\Rooms\CachedRoomRepository;
use MYVH\Rooms\RoomRepository;
use MYVH\Tests\Unit\UnitTestCase;

class CachedRoomRepositoryTest extends UnitTestCase
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

    public function test_get_by_id_returns_cached_room_after_first_lookup(): void
    {
        $repository = $this->makeRepositoryMock();
        $room = ['Id' => 15, 'Name' => 'Main Hall'];
        $repository->get_by_id_return = $room;

        $cached_repository = new CachedRoomRepository($repository);

        $first = $cached_repository->get_by_id(15);
        $second = $cached_repository->get_by_id(15);

        $this->assertSame($room, $first);
        $this->assertSame($room, $second);
        $this->assertSame([15], $repository->get_by_id_calls);
    }

    public function test_get_all_cache_is_busted_after_create_increments_version(): void
    {
        $repository = $this->makeRepositoryMock();
        $repository->get_all_return_queue = [
            [['Id' => 1, 'Name' => 'Committee Room']],
            [['Id' => 2, 'Name' => 'Small Hall']],
        ];
        $repository->create_return = 99;

        $cached_repository = new CachedRoomRepository($repository);

        $before_write_first = $cached_repository->get_all();
        $before_write_second = $cached_repository->get_all();
        $result = $cached_repository->create(['Name' => 'New Room', 'VenueId' => 5]);
        $after_write = $cached_repository->get_all();

        $this->assertSame(99, $result);
        $this->assertSame(1, $before_write_first[0]['Id']);
        $this->assertSame(1, $before_write_second[0]['Id']);
        $this->assertSame(2, $after_write[0]['Id']);
        $this->assertSame(2, $this->cache['myvh_rooms']['version']);
        $this->assertCount(2, $repository->get_all_calls);
        $this->assertSame([['Name' => 'New Room', 'VenueId' => 5]], $repository->create_calls);
    }

    public function test_get_by_venue_returns_cached_results_for_same_arguments(): void
    {
        $repository = $this->makeRepositoryMock();
        $rooms = [['Id' => 31, 'VenueId' => 8, 'Name' => 'Studio']];
        $repository->get_by_venue_return = $rooms;

        $cached_repository = new CachedRoomRepository($repository);

        $first = $cached_repository->get_by_venue(8, true);
        $second = $cached_repository->get_by_venue(8, true);

        $this->assertSame($rooms, $first);
        $this->assertSame($rooms, $second);
        $this->assertSame([
            [8, true],
        ], $repository->get_by_venue_calls);
    }

    private function makeRepositoryMock(): RoomRepositoryDouble
    {
        return new RoomRepositoryDouble(new \wpdb('', '', '', ''));
    }
}

class RoomRepositoryDouble extends RoomRepository
{
    public array $get_by_id_calls = [];
    public ?array $get_by_id_return = null;
    public array $get_all_calls = [];
    public array $get_all_return_queue = [];
    public array $get_by_venue_calls = [];
    public array $get_by_venue_return = [];
    public int|false $create_return = false;
    public array $create_calls = [];

    public function get_by_id($id): ?array
    {
        $this->get_by_id_calls[] = $id;

        return $this->get_by_id_return;
    }

    public function get_all($args = []): array
    {
        $this->get_all_calls[] = $args;

        return array_shift($this->get_all_return_queue);
    }

    public function get_by_venue($venue_id, $public_only = false): array
    {
        $this->get_by_venue_calls[] = [$venue_id, $public_only];

        return $this->get_by_venue_return;
    }

    public function create($data): int|false
    {
        $this->create_calls[] = $data;

        return $this->create_return;
    }
}

}
