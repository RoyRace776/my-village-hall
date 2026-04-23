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

use MYVH\Container\Container;
use MYVH\Pricing\CachedRoomRateRepository;
use MYVH\Pricing\PricingServiceProvider;
use MYVH\Pricing\RoomRateRepository;
use MYVH\Tests\Unit\UnitTestCase;

class PricingServiceProviderTest extends UnitTestCase
{
    public function test_room_rate_repository_binding_resolves_to_cached_repository_singleton(): void
    {
        $container = new Container();
        $wpdb = new \wpdb('', '', '', '');

        $container->singleton(\wpdb::class, function () use ($wpdb) {
            return $wpdb;
        });

        (new PricingServiceProvider())->register($container);

        $first = $container->get(RoomRateRepository::class);
        $second = $container->get(RoomRateRepository::class);

        $this->assertInstanceOf(CachedRoomRateRepository::class, $first);
        $this->assertInstanceOf(RoomRateRepository::class, $first);
        $this->assertSame($first, $second);
    }
}

}
