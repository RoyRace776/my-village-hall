<?php

require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-save-room-rate-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-room-rate-request-validator.php';

class Pricing_Service_Provider
{
    public function register($container): void {
        $container->singleton(Discount_Repository::class);
        $container->singleton(Room_Rate_Repository::class);
        $container->singleton(Pricing_Service::class);
        $container->singleton(Room_Rate_Service::class);
        $container->singleton(Room_Rate_Request_Validator::class);
        $container->singleton(Room_Rate_Controller::class);
    }
}