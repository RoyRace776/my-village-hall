<?php

class MYVH_Invoice_Service_Provider
{
    public function register($container)
    {
        $container->singleton(MYVH_Invoice_Repository::class);
        $container->singleton(MYVH_Invoice_Item_Repository::class);
        $container->singleton(MYVH_Invoice_Service::class);
        $container->singleton(MYVH_Invoice_Controller::class);
    }
}