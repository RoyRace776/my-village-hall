<?php
namespace MYVH\Availability;

use MYVH\Container\Container;

class AvailabilityServiceProvider
{
    public function register(Container $container): void {
        $container->singleton(AvailabilityService::class);
    }
}