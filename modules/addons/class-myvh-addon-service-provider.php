<?php

require_once MYVH_PLUGIN_DIR . 'modules/addons/class-myvh-save-addon-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/addons/class-myvh-addon-request-validator.php';

class Addon_Service_Provider
{
    public function register($container): void {
        $container->singleton(Addon_Repository::class);
        $container->singleton(Addon_Service::class);
        $container->singleton(Addon_Request_Validator::class);
        $container->singleton(Addon_Controller::class);
    }
}