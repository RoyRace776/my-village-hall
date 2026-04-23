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
use MYVH\Organisations\CachedOrganisationTypeRepository;
use MYVH\Organisations\OrganisationTypeRepository;
use MYVH\Tests\Unit\UnitTestCase;

class CachedOrganisationTypeRepositoryTest extends UnitTestCase
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

    public function test_get_by_name_returns_cached_value_after_first_lookup(): void
    {
        $repository = $this->makeRepositoryMock();
        $type = ['Id' => 8, 'Name' => 'Charity'];
        $repository->get_by_name_return = $type;

        $cached_repository = new CachedOrganisationTypeRepository($repository);

        $first = $cached_repository->get_by_name('Charity');
        $second = $cached_repository->get_by_name('Charity');

        $this->assertSame($type, $first);
        $this->assertSame($type, $second);
        $this->assertSame(['Charity'], $repository->get_by_name_calls);
    }

    public function test_get_all_cache_is_busted_after_create_increments_version(): void
    {
        $repository = $this->makeRepositoryMock();
        $repository->get_all_return_queue = [
            [['Id' => 1, 'Name' => 'Person']],
            [['Id' => 2, 'Name' => 'Club']],
        ];
        $repository->create_return = 12;

        $cached_repository = new CachedOrganisationTypeRepository($repository);

        $before_write = $cached_repository->get_all();
        $cached_repository->get_all();
        $result = $cached_repository->create(['Name' => 'Group']);
        $after_write = $cached_repository->get_all();

        $this->assertSame(12, $result);
        $this->assertSame(1, $before_write[0]['Id']);
        $this->assertSame(2, $after_write[0]['Id']);
        $this->assertSame(2, $this->cache['myvh_organisation_types']['version']);
        $this->assertCount(2, $repository->get_all_calls);
    }

    private function makeRepositoryMock(): OrganisationTypeRepositoryDouble
    {
        return new OrganisationTypeRepositoryDouble(new \wpdb('', '', '', ''));
    }
}

class OrganisationTypeRepositoryDouble extends OrganisationTypeRepository
{
    public ?array $get_by_name_return = null;
    public array $get_by_name_calls = [];
    public array $get_all_return_queue = [];
    public array $get_all_calls = [];
    public int|false $create_return = false;

    public function get_by_name(string $name): ?array
    {
        $this->get_by_name_calls[] = $name;

        return $this->get_by_name_return;
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
