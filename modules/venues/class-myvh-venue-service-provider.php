<?php

class MYVH_Venue_Service_Provider
{
    public function register($container)
    {
        $container->singleton(MYVH_Venue_Repository::class);
        $container->singleton(MYVH_Venue_Service::class);
        $container->singleton(MYVH_Venue_Controller::class);
    }
}