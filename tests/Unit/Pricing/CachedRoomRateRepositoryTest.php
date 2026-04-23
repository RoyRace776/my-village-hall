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

namespace MYVH\Tests\Unit\Pricing {

use Brain\Monkey\Functions;
use MYVH\Pricing\CachedRoomRateRepository;
use MYVH\Pricing\RoomRateRepository;
use MYVH\Tests\Unit\UnitTestCase;

class CachedRoomRateRepositoryTest extends UnitTestCase
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

    public function test_get_active_room_rate_returns_cached_result_after_first_lookup(): void
    {
        $repository = $this->makeRepositoryMock();
        $rate = ['Id' => 10, 'RoomId' => 3, 'Name' => 'Standard'];
        $repository->get_active_room_rate_return = $rate;

        $cached_repository = new CachedRoomRateRepository($repository);

        $first = $cached_repository->get_active_room_rate(3, 2);
        $second = $cached_repository->get_active_room_rate(3, 2);

        $this->assertSame($rate, $first);
        $this->assertSame($rate, $second);
        $this->assertSame([[3, 2]], $repository->get_active_room_rate_calls);
    }

    public function test_get_all_cache_is_busted_after_create_increments_version(): void
    {
        $repository = $this->makeRepositoryMock();
        $repository->get_all_return_queue = [
            [['Id' => 1, 'Name' => 'Rate A']],
            [['Id' => 2, 'Name' => 'Rate B']],
        ];
        $repository->create_return = 55;

        $cached_repository = new CachedRoomRateRepository($repository);

        $before_write = $cached_repository->get_all();
        $cached_repository->get_all();
        $result = $cached_repository->create(['RoomId' => 2, 'Name' => 'New Rate']);
        $after_write = $cached_repository->get_all();

        $this->assertSame(55, $result);
        $this->assertSame(1, $before_write[0]['Id']);
        $this->assertSame(2, $after_write[0]['Id']);
        $this->assertSame(2, $this->cache['myvh_room_rates']['version']);
        $this->assertCount(2, $repository->get_all_calls);
    }

    private function makeRepositoryMock(): RoomRateRepositoryDouble
    {
        return new RoomRateRepositoryDouble(new \wpdb('', '', '', ''));
    }
}

class RoomRateRepositoryDouble extends RoomRateRepository
{
    public ?array $get_active_room_rate_return = null;
    public array $get_active_room_rate_calls = [];
    public array $get_all_return_queue = [];
    public array $get_all_calls = [];
    public int|false $create_return = false;

    public function get_active_room_rate($room_id, $org_type_id = null): ?array
    {
        $this->get_active_room_rate_calls[] = [$room_id, $org_type_id];

        return $this->get_active_room_rate_return;
    }

    public function get_all($args = []): array
    {
        $this->get_all_calls[] = $args;

        return array_shift($this->get_all_return_queue);
    }

    public function create($data): int|false
    {
        return $this->create_return;
    }
}

}
