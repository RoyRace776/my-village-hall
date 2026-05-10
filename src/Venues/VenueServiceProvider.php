<?php
namespace MYVH\Venues;

use MYVH\Container\Container;

class VenueServiceProvider
{
    public function register(Container $container): void {
        $container->singleton(VenueRepository::class, function ($container) {
            $wpdb = $container->get(\wpdb::class);
            $repository = new VenueRepository($wpdb);

            return new CachedVenueRepository($repository);
        });
        $container->singleton(VenueHoursRepository::class, function ($container) {
            $wpdb = $container->get(\wpdb::class);
            $repository = new VenueHoursRepository($wpdb);

            return new CachedVenueHoursRepository($repository);
        });
        $container->singleton(VenueService::class);
        $container->singleton(VenueRequestValidator::class);
        $container->singleton(VenueController::class);
    }
}