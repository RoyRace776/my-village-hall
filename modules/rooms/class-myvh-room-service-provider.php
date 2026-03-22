<?php

require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-save-room-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-room-request-validator.php';

class MYVH_Room_Service_Provider
{
    public function register($container): void {
        $container->singleton(MYVH_Room_Repository::class);
        $container->singleton(MYVH_Room_Rules_Service::class);
        $container->singleton(MYVH_Room_Service::class);
        $container->singleton(MYVH_Room_Request_Validator::class);
        $container->singleton(MYVH_Room_Controller::class);
    }
}