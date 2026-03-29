<?php
namespace MYVH\Venues;

require_once MYVH_PLUGIN_DIR . 'modules/venues/SaveVenueRequest.php';
require_once MYVH_PLUGIN_DIR . 'modules/venues/VenueRequestValidator.php';

class VenueServiceProvider
{
    public function register($container): void {
        $container->singleton(VenueRepository::class);
        $container->singleton(VenueService::class);
        $container->singleton(VenueRequestValidator::class);
        $container->singleton(VenueController::class);
    }
}