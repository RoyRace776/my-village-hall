<?php

class Calendar_Service_Provider
{
    public function register($container): void {
        $container->singleton(Calendar_Service::class);
        $container->singleton(Calendar_Ajax_Controller::class);
    }
}