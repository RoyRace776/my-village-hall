<?php

class MYVH_Customer_Group_Service_Provider
{
    public function register($container)
    {
        $container->singleton(MYVH_Customer_Group_Repository::class);
        $container->singleton(MYVH_Customer_Group_Service::class);
        $container->singleton(MYVH_Customer_Group_Controller::class);
    }
}