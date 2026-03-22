<?php

require_once MYVH_PLUGIN_DIR . 'modules/customers/class-myvh-save-customer-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/customers/class-myvh-customer-request-validator.php';

class MYVH_Customer_Service_Provider
{
    public function register($container): void {
        $container->singleton(MYVH_Customer_Repository::class);
        $container->singleton(MYVH_Customer_Service::class);
        $container->singleton(MYVH_Customer_Request_Validator::class);
        $container->singleton(MYVH_Customer_User_Sync::class);
        $container->singleton(MYVH_Customer_Controller::class);
    }
}