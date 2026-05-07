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

use MYVH\Container\Container;
use MYVH\Rooms\CachedRoomHoursRepository;
use MYVH\Rooms\CachedRoomRepository;
use MYVH\Rooms\RoomDepositRepository;
use MYVH\Rooms\RoomHoursRepository;
use MYVH\Rooms\RoomRepository;
use MYVH\Rooms\RoomServiceProvider;
use MYVH\Tests\Unit\UnitTestCase;

class RoomServiceProviderTest extends UnitTestCase
{
    public function test_room_repository_binding_resolves_to_cached_repository_singleton(): void
    {
        $container = new Container();
        $wpdb = new \wpdb('', '', '', '');
        $wpdb->prefix = 'wp_';

        $container->singleton(\wpdb::class, function () use ($wpdb) {
            return $wpdb;
        });

        (new RoomServiceProvider())->register($container);

        $first = $container->get(RoomRepository::class);
        $second = $container->get(RoomRepository::class);

        $this->assertInstanceOf(CachedRoomRepository::class, $first);
        $this->assertInstanceOf(RoomRepository::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_room_hours_repository_binding_resolves_to_cached_repository_singleton(): void
    {
        $container = new Container();
        $wpdb = new \wpdb('', '', '', '');
        $wpdb->prefix = 'wp_';

        $container->singleton(\wpdb::class, function () use ($wpdb) {
            return $wpdb;
        });

        (new RoomServiceProvider())->register($container);

        $first = $container->get(RoomHoursRepository::class);
        $second = $container->get(RoomHoursRepository::class);

        $this->assertInstanceOf(CachedRoomHoursRepository::class, $first);
        $this->assertInstanceOf(RoomHoursRepository::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_room_deposit_repository_binding_resolves_singleton(): void
    {
        $container = new Container();
        $wpdb = new \wpdb('', '', '', '');
        $wpdb->prefix = 'wp_';

        $container->singleton(\wpdb::class, function () use ($wpdb) {
            return $wpdb;
        });

        (new RoomServiceProvider())->register($container);

        $first = $container->get(RoomDepositRepository::class);
        $second = $container->get(RoomDepositRepository::class);

        $this->assertInstanceOf(RoomDepositRepository::class, $first);
        $this->assertSame($first, $second);
    }
}

}
