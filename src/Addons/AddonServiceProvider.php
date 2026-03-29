<?php
namespace MYVH\Addons;

class AddonServiceProvider
{
    public function register($container): void {
        $container->singleton(AddonRepository::class);
        $container->singleton(AddonService::class);
        $container->singleton(AddonRequestValidator::class);
        $container->singleton(AddonController::class);
    }
}