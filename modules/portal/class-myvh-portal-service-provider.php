<?php
/**
 * Service provider for the Portal domain
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once MYVH_PLUGIN_DIR . 'modules/portal/class-myvh-portal-controller.php';

class PortalServiceProvider {
    public function register($container): void {
        $container->singleton(PortalController::class);
    }
}
