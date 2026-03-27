<?php

require_once MYVH_PLUGIN_DIR . 'modules/customers/class-myvh-save-customer-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/customers/class-myvh-customer-request-validator.php';

class Customer_Service_Provider
{
    public function register($container): void {
        $container->singleton(Customer_Repository::class);
        $container->singleton(Customer_Service::class);
        $container->singleton(Customer_Request_Validator::class);
        $container->singleton(Customer_User_Sync::class);
        $container->singleton(Customer_Controller::class);
    }
}