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

namespace MYVH\Tests\Unit\Venues {

use Brain\Monkey\Functions;
use MYVH\Tests\Unit\UnitTestCase;
use MYVH\Venues\CachedVenueRepository;
use MYVH\Venues\VenueRepository;

class CachedVenueRepositoryTest extends UnitTestCase
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

    public function test_get_by_id_returns_cached_venue_after_first_lookup(): void
    {
        $repository = $this->makeRepositoryMock();
        $venue = ['Id' => 21, 'Name' => 'Village Hall'];
        $repository->get_by_id_return = $venue;

        $cached_repository = new CachedVenueRepository($repository);

        $first = $cached_repository->get_by_id(21);
        $second = $cached_repository->get_by_id(21);

        $this->assertSame($venue, $first);
        $this->assertSame($venue, $second);
        $this->assertSame([21], $repository->get_by_id_calls);
    }

    public function test_get_all_cache_is_busted_after_create_increments_version(): void
    {
        $repository = $this->makeRepositoryMock();
        $repository->get_all_return_queue = [
            [['Id' => 1, 'Name' => 'Main']],
            [['Id' => 2, 'Name' => 'Annexe']],
        ];
        $repository->create_return = 77;

        $cached_repository = new CachedVenueRepository($repository);

        $before_write = $cached_repository->get_all();
        $cached_repository->get_all();
        $result = $cached_repository->create(['Name' => 'New Venue']);
        $after_write = $cached_repository->get_all();

        $this->assertSame(77, $result);
        $this->assertSame(1, $before_write[0]['Id']);
        $this->assertSame(2, $after_write[0]['Id']);
        $this->assertSame(2, $this->cache['myvh_venues']['version']);
        $this->assertCount(2, $repository->get_all_calls);
    }

    private function makeRepositoryMock(): VenueRepositoryDouble
    {
        return new VenueRepositoryDouble(new \wpdb('', '', '', ''));
    }
}

class VenueRepositoryDouble extends VenueRepository
{
    public ?array $get_by_id_return = null;
    public array $get_by_id_calls = [];
    public array $get_all_return_queue = [];
    public array $get_all_calls = [];
    public int|false $create_return = false;

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

    public function create($data): int|false
    {
        return $this->create_return;
    }
}

}
