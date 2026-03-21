<?php

class MYVH_Room_Service_Provider
{
    public function register($container): void {
        $container->singleton(MYVH_Room_Repository::class);
        $container->singleton(MYVH_Room_Rules_Service::class);
        $container->singleton(MYVH_Room_Service::class);
        $container->singleton(MYVH_Room_Controller::class);
    }
}