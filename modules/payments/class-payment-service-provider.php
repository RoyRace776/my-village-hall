<?php

class MYVH_Payment_Service_Provider
{
    public function register($container)
    {
        $container->singleton(MYVH_Payment_Repository::class);
    }
}