<?php
namespace MYVH\Venues;

class VenueServiceProvider
{
    public function register($container): void {
        $container->singleton(VenueRepository::class, function ($container) {
            $wpdb = $container->get(\wpdb::class);
            $repository = new VenueRepository($wpdb);

            return new CachedVenueRepository($repository);
        });
        $container->singleton(VenueHoursRepository::class);
        $container->singleton(VenueService::class);
        $container->singleton(VenueRequestValidator::class);
        $container->singleton(VenueController::class);
    }
}