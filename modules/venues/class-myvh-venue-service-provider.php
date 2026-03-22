<?php

require_once MYVH_PLUGIN_DIR . 'modules/venues/class-myvh-save-venue-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/venues/class-myvh-venue-request-validator.php';

class MYVH_Venue_Service_Provider
{
    public function register($container): void {
        $container->singleton(MYVH_Venue_Repository::class);
        $container->singleton(MYVH_Venue_Service::class);
        $container->singleton(MYVH_Venue_Request_Validator::class);
        $container->singleton(MYVH_Venue_Controller::class);
    }
}