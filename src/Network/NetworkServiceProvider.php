<?php
namespace MYVH\Network;

use MYVH\Application\Services\CreateSiteRenderer;
use MYVH\Container\Container;

if (!defined('ABSPATH')) {
    exit;
}

class NetworkServiceProvider {
    public function register(Container $container): void {
        $container->singleton(CreateSiteRenderer::class);
        $container->singleton(CreateSiteRequestValidator::class);
        //$container->singleton(NsClonerAdapter::class);
        $container->singleton(SiteProvisioningService::class);
        $container->singleton(CreateSiteShortcode::class);
    }
}
