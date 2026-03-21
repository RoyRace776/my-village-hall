<?php

class MYVH_Pricing_Service_Provider
{
    public function register($container): void {
        $container->singleton(MYVH_Discount_Repository::class);
        $container->singleton(MYVH_Room_Rate_Repository::class);
        $container->singleton(MYVH_Pricing_Service::class);
        $container->singleton(MYVH_Room_Rate_Service::class);
        $container->singleton(MYVH_Room_Rate_Controller::class);
    }
}