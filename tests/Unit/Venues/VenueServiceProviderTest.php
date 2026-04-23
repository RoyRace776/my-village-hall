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

use MYVH\Container\Container;
use MYVH\Tests\Unit\UnitTestCase;
use MYVH\Venues\CachedVenueHoursRepository;
use MYVH\Venues\CachedVenueRepository;
use MYVH\Venues\VenueHoursRepository;
use MYVH\Venues\VenueRepository;
use MYVH\Venues\VenueServiceProvider;

class VenueServiceProviderTest extends UnitTestCase
{
    public function test_venue_repository_binding_resolves_to_cached_repository_singleton(): void
    {
        $container = new Container();
        $wpdb = new \wpdb('', '', '', '');

        $container->singleton(\wpdb::class, function () use ($wpdb) {
            return $wpdb;
        });

        (new VenueServiceProvider())->register($container);

        $first = $container->get(VenueRepository::class);
        $second = $container->get(VenueRepository::class);

        $this->assertInstanceOf(CachedVenueRepository::class, $first);
        $this->assertInstanceOf(VenueRepository::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_venue_hours_repository_binding_resolves_to_cached_repository_singleton(): void
    {
        $container = new Container();
        $wpdb = new \wpdb('', '', '', '');

        $container->singleton(\wpdb::class, function () use ($wpdb) {
            return $wpdb;
        });

        (new VenueServiceProvider())->register($container);

        $first = $container->get(VenueHoursRepository::class);
        $second = $container->get(VenueHoursRepository::class);

        $this->assertInstanceOf(CachedVenueHoursRepository::class, $first);
        $this->assertInstanceOf(VenueHoursRepository::class, $first);
        $this->assertSame($first, $second);
    }
}

}
