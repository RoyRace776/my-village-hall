<?php

class MYVH_Payment_Service_Provider
{
    public function register($container): void {
        $container->singleton(MYVH_Payment_Repository::class);
    }
}