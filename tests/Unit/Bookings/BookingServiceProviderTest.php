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

namespace MYVH\Tests\Unit\Bookings {

use MYVH\Bookings\BookingRepository;
use MYVH\Bookings\BookingServiceProvider;
use MYVH\Bookings\CachedBookingRepository;
use MYVH\Container\Container;
use MYVH\Tests\Unit\UnitTestCase;

class BookingServiceProviderTest extends UnitTestCase
{
    public function test_booking_repository_binding_resolves_to_cached_repository_singleton(): void
    {
        $container = new Container();
        $wpdb = new \wpdb('', '', '', '');
        $wpdb->prefix = 'wp_';

        $container->singleton(\wpdb::class, function () use ($wpdb) {
            return $wpdb;
        });

        (new BookingServiceProvider())->register($container);

        $first = $container->get(BookingRepository::class);
        $second = $container->get(BookingRepository::class);

        $this->assertInstanceOf(CachedBookingRepository::class, $first);
        $this->assertInstanceOf(BookingRepository::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_cached_repository_uses_wrapped_repository_built_with_container_wpdb(): void
    {
        $container = new Container();
        $wpdb = new \wpdb('', '', '', '');
        $wpdb->prefix = 'wp_custom_';

        $container->singleton(\wpdb::class, function () use ($wpdb) {
            return $wpdb;
        });

        (new BookingServiceProvider())->register($container);

        $resolved = $container->get(BookingRepository::class);

        $this->assertInstanceOf(CachedBookingRepository::class, $resolved);
        $this->assertInstanceOf(BookingRepository::class, $resolved);
    }
}

}