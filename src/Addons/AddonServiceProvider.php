<?php
namespace MYVH\Addons;

use MYVH\Container\Container;

class AddonServiceProvider
{
    public function register(Container $container): void {
        $container->singleton(AddonRepository::class, function ($container) {
            $wpdb = $container->get(\wpdb::class);
            $repository = new AddonRepository($wpdb);

            return new CachedAddonRepository($repository);
        });
        $container->singleton(AddonService::class);
        $container->singleton(AddonRequestValidator::class);
        $container->singleton(AddonController::class);
    }
}