<?php
namespace MYVH\Availability;

class AvailabilityServiceProvider
{
    public function register($container): void {
        $container->singleton(AvailabilityService::class);
    }
}