<?php

class MYVH_Calendar_Service_Provider
{
    public function register($container)
    {
        $container->singleton(MYVH_Calendar_Service::class);
        $container->singleton(MYVH_Calendar_Ajax_Controller::class);
    }
}