<?php
namespace MYVH\Rooms;

use MYVH\Container\Container;

class RoomServiceProvider
{
    public function register(Container $container): void {
        $container->singleton(RoomRepository::class, function ($container) {
            $wpdb = $container->get(\wpdb::class);
            $repository = new RoomRepository($wpdb);

            return new CachedRoomRepository($repository);
        });
        $container->singleton(RoomHoursRepository::class, function ($container) {
            $wpdb = $container->get(\wpdb::class);
            $repository = new RoomHoursRepository($wpdb);

            return new CachedRoomHoursRepository($repository);
        });
        $container->singleton(RoomRulesService::class);
        $container->singleton(RoomDepositRepository::class);
        $container->singleton(RoomService::class);
        $container->singleton(RoomVisibilityService::class);
        $container->singleton(RoomRequestValidator::class);
        $container->singleton(RoomController::class);
    }
}