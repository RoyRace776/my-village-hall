<?php
namespace MYVH\Addons;

use MYVH\Container\Container;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AddonServiceProvider
{
    public function register(Container $container): void {
        $container->singleton(AddonRepository::class, function ($container) {
            $wpdb = $container->get(\wpdb::class);
            try {
                $logger = $container->get(LoggerInterface::class);
            } catch (\Exception $e) {
                $logger = new NullLogger();
            }
            $repository = new AddonRepository(
                $wpdb,
                $logger
            );

            return new CachedAddonRepository($repository);
        });
        $container->singleton(AddonService::class);
        $container->singleton(AddonRequestValidator::class);
        $container->singleton(AddonController::class);
    }
}