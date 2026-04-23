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

use MYVH\Addons\AddonRepository;
use MYVH\Addons\AddonServiceProvider;
use MYVH\Addons\CachedAddonRepository;
use MYVH\Container\Container;
use MYVH\Tests\Unit\UnitTestCase;

class AddonServiceProviderTest extends UnitTestCase
{
    public function test_addon_repository_binding_resolves_to_cached_repository_singleton(): void
    {
        $container = new Container();
        $wpdb = new \wpdb('', '', '', '');

        $container->singleton(\wpdb::class, function () use ($wpdb) {
            return $wpdb;
        });

        (new AddonServiceProvider())->register($container);

        $first = $container->get(AddonRepository::class);
        $second = $container->get(AddonRepository::class);

        $this->assertInstanceOf(CachedAddonRepository::class, $first);
        $this->assertInstanceOf(AddonRepository::class, $first);
        $this->assertSame($first, $second);
    }
}

}
