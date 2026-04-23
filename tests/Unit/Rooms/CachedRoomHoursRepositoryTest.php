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
use MYVH\Rooms\CachedRoomHoursRepository;
use MYVH\Rooms\RoomHoursRepository;
use MYVH\Tests\Unit\UnitTestCase;

class CachedRoomHoursRepositoryTest extends UnitTestCase
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

    public function test_get_by_room_returns_cached_rows_after_first_lookup(): void
    {
        $repository = $this->makeRepositoryMock();
        $rows = [['RoomId' => 1, 'DayOfWeek' => 1, 'IsClosed' => 0]];
        $repository->get_by_room_return = $rows;

        $cached_repository = new CachedRoomHoursRepository($repository);

        $first = $cached_repository->get_by_room(1);
        $second = $cached_repository->get_by_room(1);

        $this->assertSame($rows, $first);
        $this->assertSame($rows, $second);
        $this->assertSame([1], $repository->get_by_room_calls);
    }

    public function test_cache_is_busted_after_replace_for_room(): void
    {
        $repository = $this->makeRepositoryMock();
        $repository->get_by_room_return_queue = [
            [['RoomId' => 2, 'DayOfWeek' => 1]],
            [['RoomId' => 2, 'DayOfWeek' => 2]],
        ];
        $repository->replace_for_room_return = true;

        $cached_repository = new CachedRoomHoursRepository($repository);

        $before = $cached_repository->get_by_room(2);
        $cached_repository->get_by_room(2);
        $result = $cached_repository->replace_for_room(2, [['day_of_week' => 1, 'is_closed' => 0]]);
        $after = $cached_repository->get_by_room(2);

        $this->assertTrue($result);
        $this->assertSame(1, $before[0]['DayOfWeek']);
        $this->assertSame(2, $after[0]['DayOfWeek']);
        $this->assertSame(2, $this->cache['myvh_room_hours']['version']);
        $this->assertSame([], $repository->get_by_room_calls);
        $this->assertCount(2, $repository->get_by_room_queue_calls);
    }

    private function makeRepositoryMock(): RoomHoursRepositoryDouble
    {
        return new RoomHoursRepositoryDouble(new \wpdb('', '', '', ''));
    }
}

class RoomHoursRepositoryDouble extends RoomHoursRepository
{
    public array $get_by_room_calls = [];
    public array $get_by_room_queue_calls = [];
    public array $get_by_room_return = [];
    public array $get_by_room_return_queue = [];
    public bool $replace_for_room_return = false;

    public function get_by_room(int $room_id): array
    {
        if (!empty($this->get_by_room_return_queue)) {
            $this->get_by_room_queue_calls[] = $room_id;
            return array_shift($this->get_by_room_return_queue);
        }

        $this->get_by_room_calls[] = $room_id;
        return $this->get_by_room_return;
    }

    public function replace_for_room(int $room_id, array $rows): bool
    {
        return $this->replace_for_room_return;
    }
}

}
