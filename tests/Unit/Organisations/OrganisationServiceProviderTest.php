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

use MYVH\Container\Container;
use MYVH\Organisations\CachedOrganisationRepository;
use MYVH\Organisations\CachedOrganisationTypeRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Organisations\OrganisationServiceProvider;
use MYVH\Organisations\OrganisationTypeRepository;
use MYVH\Tests\Unit\UnitTestCase;

class OrganisationServiceProviderTest extends UnitTestCase
{
    public function test_organisation_repository_binding_resolves_to_cached_repository_singleton(): void
    {
        $container = new Container();
        $wpdb = new \wpdb('', '', '', '');

        $container->singleton(\wpdb::class, function () use ($wpdb) {
            return $wpdb;
        });

        (new OrganisationServiceProvider())->register($container);

        $first = $container->get(OrganisationRepository::class);
        $second = $container->get(OrganisationRepository::class);

        $this->assertInstanceOf(CachedOrganisationRepository::class, $first);
        $this->assertInstanceOf(OrganisationRepository::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_organisation_type_repository_binding_resolves_to_cached_repository_singleton(): void
    {
        $container = new Container();
        $wpdb = new \wpdb('', '', '', '');

        $container->singleton(\wpdb::class, function () use ($wpdb) {
            return $wpdb;
        });

        (new OrganisationServiceProvider())->register($container);

        $first = $container->get(OrganisationTypeRepository::class);
        $second = $container->get(OrganisationTypeRepository::class);

        $this->assertInstanceOf(CachedOrganisationTypeRepository::class, $first);
        $this->assertInstanceOf(OrganisationTypeRepository::class, $first);
        $this->assertSame($first, $second);
    }
}

}
