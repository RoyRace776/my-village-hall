<?php

class Availability_Service_Provider
{
    public function register($container): void {
        $container->singleton(Availability_Service::class);
    }
}