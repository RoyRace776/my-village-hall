<?php
namespace MYVH\Rooms;

class RoomServiceProvider
{
    public function register($container): void {
        $container->singleton(RoomRepository::class);
        $container->singleton(RoomRulesService::class);
        $container->singleton(RoomService::class);
        $container->singleton(RoomVisibilityService::class);
        $container->singleton(RoomRequestValidator::class);
        $container->singleton(RoomController::class);
    }
}