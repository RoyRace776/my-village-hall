<?php
namespace MYVH\Network;

if (!defined('ABSPATH')) {
    exit;
}

class NetworkServiceProvider {
    public function register($container): void {
        $container->singleton(CreateSiteRequestValidator::class);
        //$container->singleton(NsClonerAdapter::class);
        $container->singleton(SiteProvisioningService::class);
        $container->singleton(CreateSiteShortcode::class);
    }
}
