<?php

require_once MYVH_PLUGIN_DIR . 'modules/venues/class-myvh-save-venue-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/venues/class-myvh-venue-request-validator.php';

class VenueServiceProvider
{
    public function register($container): void {
        $container->singleton(VenueRepository::class);
        $container->singleton(VenueService::class);
        $container->singleton(VenueRequestValidator::class);
        $container->singleton(VenueController::class);
    }
}