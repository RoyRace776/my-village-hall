<?php

require_once MYVH_PLUGIN_DIR . 'modules/venues/class-myvh-save-venue-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/venues/class-myvh-venue-request-validator.php';

class Venue_Service_Provider
{
    public function register($container): void {
        $container->singleton(Venue_Repository::class);
        $container->singleton(Venue_Service::class);
        $container->singleton(Venue_Request_Validator::class);
        $container->singleton(Venue_Controller::class);
    }
}