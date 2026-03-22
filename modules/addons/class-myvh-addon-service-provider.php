<?php

require_once MYVH_PLUGIN_DIR . 'modules/addons/class-myvh-save-addon-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/addons/class-myvh-addon-request-validator.php';

class MYVH_Addon_Service_Provider
{
    public function register($container): void {
        $container->singleton(MYVH_Addon_Repository::class);
        $container->singleton(MYVH_Addon_Service::class);
        $container->singleton(MYVH_Addon_Request_Validator::class);
        $container->singleton(MYVH_Addon_Controller::class);
    }
}