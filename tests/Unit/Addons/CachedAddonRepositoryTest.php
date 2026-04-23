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

namespace MYVH\Tests\Unit\Addons {

use Brain\Monkey\Functions;
use MYVH\Addons\AddonRepository;
use MYVH\Addons\CachedAddonRepository;
use MYVH\Tests\Unit\UnitTestCase;

class CachedAddonRepositoryTest extends UnitTestCase
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

    public function test_get_active_by_id_returns_cached_addon_after_first_lookup(): void
    {
        $repository = $this->makeRepositoryMock();
        $addon = ['Id' => 11, 'Name' => 'Projector'];
        $repository->get_active_by_id_return = $addon;

        $cached_repository = new CachedAddonRepository($repository);

        $first = $cached_repository->get_active_by_id(11);
        $second = $cached_repository->get_active_by_id(11);

        $this->assertSame($addon, $first);
        $this->assertSame($addon, $second);
        $this->assertSame([11], $repository->get_active_by_id_calls);
    }

    public function test_get_all_active_cache_is_busted_after_archive(): void
    {
        $repository = $this->makeRepositoryMock();
        $repository->get_all_active_return_queue = [
            [['Id' => 1, 'Name' => 'Tea']],
            [['Id' => 2, 'Name' => 'Coffee']],
        ];
        $repository->archive_by_id_return = true;

        $cached_repository = new CachedAddonRepository($repository);

        $before_write = $cached_repository->get_all_active();
        $cached_repository->get_all_active();
        $result = $cached_repository->archive_by_id(1);
        $after_write = $cached_repository->get_all_active();

        $this->assertTrue($result);
        $this->assertSame(1, $before_write[0]['Id']);
        $this->assertSame(2, $after_write[0]['Id']);
        $this->assertSame(2, $this->cache['myvh_addons']['version']);
        $this->assertCount(2, $repository->get_all_active_calls);
        $this->assertSame([1], $repository->archive_by_id_calls);
    }

    private function makeRepositoryMock(): AddonRepositoryDouble
    {
        return new AddonRepositoryDouble(new \wpdb('', '', '', ''));
    }
}

class AddonRepositoryDouble extends AddonRepository
{
    public ?array $get_active_by_id_return = null;
    public array $get_active_by_id_calls = [];
    public array $get_all_active_return_queue = [];
    public array $get_all_active_calls = [];
    public array $archive_by_id_calls = [];
    public bool $archive_by_id_return = false;

    public function get_active_by_id(int $id): ?array
    {
        $this->get_active_by_id_calls[] = $id;

        return $this->get_active_by_id_return;
    }

    public function get_all_active(array $args = []): array
    {
        $this->get_all_active_calls[] = $args;

        return array_shift($this->get_all_active_return_queue);
    }

    public function archive_by_id(int $id): bool
    {
        $this->archive_by_id_calls[] = $id;

        return $this->archive_by_id_return;
    }
}

}
