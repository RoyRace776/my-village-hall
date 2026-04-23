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

namespace MYVH\Tests\Unit\Organisations {

use Brain\Monkey\Functions;
use MYVH\Organisations\CachedOrganisationRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Tests\Unit\UnitTestCase;

class CachedOrganisationRepositoryTest extends UnitTestCase
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

    public function test_get_default_returns_cached_value_after_first_lookup(): void
    {
        $repository = $this->makeRepositoryMock();
        $default = ['Id' => 3, 'Name' => 'Residents'];
        $repository->get_default_return = $default;

        $cached_repository = new CachedOrganisationRepository($repository);

        $first = $cached_repository->get_default();
        $second = $cached_repository->get_default();

        $this->assertSame($default, $first);
        $this->assertSame($default, $second);
        $this->assertSame(1, $repository->get_default_calls);
    }

    public function test_get_all_cache_is_busted_after_create_increments_version(): void
    {
        $repository = $this->makeRepositoryMock();
        $repository->get_all_return_queue = [
            [['Id' => 1, 'Name' => 'A']],
            [['Id' => 2, 'Name' => 'B']],
        ];
        $repository->create_return = 44;

        $cached_repository = new CachedOrganisationRepository($repository);

        $before_write = $cached_repository->get_all();
        $cached_repository->get_all();
        $result = $cached_repository->create(['Name' => 'New Org']);
        $after_write = $cached_repository->get_all();

        $this->assertSame(44, $result);
        $this->assertSame(1, $before_write[0]['Id']);
        $this->assertSame(2, $after_write[0]['Id']);
        $this->assertSame(2, $this->cache['myvh_organisations']['version']);
        $this->assertCount(2, $repository->get_all_calls);
    }

    private function makeRepositoryMock(): OrganisationRepositoryDouble
    {
        return new OrganisationRepositoryDouble(new \wpdb('', '', '', ''));
    }
}

class OrganisationRepositoryDouble extends OrganisationRepository
{
    public ?array $get_default_return = null;
    public int $get_default_calls = 0;
    public array $get_all_return_queue = [];
    public array $get_all_calls = [];
    public int|false $create_return = false;

    public function get_default(): ?array
    {
        $this->get_default_calls++;

        return $this->get_default_return;
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
