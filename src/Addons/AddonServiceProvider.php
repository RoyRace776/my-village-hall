<?php
namespace MYVH\Addons;

class AddonServiceProvider
{
    public function register($container): void {
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