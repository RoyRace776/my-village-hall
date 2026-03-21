<?php

class MYVH_Availability_Service_Provider
{
    public function register($container)
    {
        $container->singleton(MYVH_Availability_Service::class);
    }
}