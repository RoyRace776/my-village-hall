<?php

class MYVH_Customer_Service_Provider
{
    public function register($container)
    {
        $container->singleton(MYVH_Customer_Repository::class);
        $container->singleton(MYVH_Customer_Service::class);
        $container->singleton(MYVH_Customer_Controller::class);
    }
}