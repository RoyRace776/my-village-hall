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

class MYVH_Portal_Service_Provider {
    public function register($container): void {
        $container->singleton(MYVH_Portal_Controller::class);
    }
}
