<?php
namespace MYVH\Venues;

class VenueServiceProvider
{
    public function register($container): void {
        $container->singleton(VenueRepository::class);
        $container->singleton(VenueService::class);
        $container->singleton(VenueRequestValidator::class);
        $container->singleton(VenueController::class);
    }
}