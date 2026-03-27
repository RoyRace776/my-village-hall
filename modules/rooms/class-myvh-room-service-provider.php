<?php

require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-save-room-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-room-request-validator.php';

class Room_Service_Provider
{
    public function register($container): void {
        $container->singleton(Room_Repository::class);
        $container->singleton(Room_Rules_Service::class);
        $container->singleton(Room_Service::class);
        $container->singleton(Room_Request_Validator::class);
        $container->singleton(Room_Controller::class);
    }
}