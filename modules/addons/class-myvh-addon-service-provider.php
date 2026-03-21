<?php

class MYVH_Addon_Service_Provider
{
    public function register($container): void {
        $container->singleton(MYVH_Addon_Repository::class);
        $container->singleton(MYVH_Addon_Service::class);
        $container->singleton(MYVH_Addon_Controller::class);
    }
}