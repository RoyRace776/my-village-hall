<?php

class Payment_Service_Provider
{
    public function register($container): void {
        $container->singleton(Payment_Repository::class);
    }
}