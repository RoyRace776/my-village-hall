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

class Portal_Service_Provider {
    public function register($container): void {
        $container->singleton(Portal_Controller::class);
    }
}
