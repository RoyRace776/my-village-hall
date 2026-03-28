<?php

require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-save-room-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-myvh-room-request-validator.php';

class RoomServiceProvider
{
    public function register($container): void {
        $container->singleton(RoomRepository::class);
        $container->singleton(RoomRulesService::class);
        $container->singleton(RoomService::class);
        $container->singleton(RoomRequestValidator::class);
        $container->singleton(RoomController::class);
    }
}