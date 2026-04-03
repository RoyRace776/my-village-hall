<?php
namespace MYVH\Portal;

/**
 * Service provider for the Portal domain
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}


class PortalServiceProvider {
    public function register($container): void {
        $container->singleton(PortalController::class);
        $container->singleton(PortalBootstrapDataService::class);
        $container->singleton(PortalShortcode::class);
    }
}
