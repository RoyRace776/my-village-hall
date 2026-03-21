<?php

class MYVH_Availability_Service_Provider
{
    public function register($container): void {
        $container->singleton(MYVH_Availability_Service::class);
    }
}