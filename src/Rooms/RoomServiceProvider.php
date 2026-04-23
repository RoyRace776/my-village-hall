<?php
namespace MYVH\Rooms;

class RoomServiceProvider
{
    public function register($container): void {
        $container->singleton(RoomRepository::class, function ($container) {
            $wpdb = $container->get(\wpdb::class);
            $repository = new RoomRepository($wpdb);

            return new CachedRoomRepository($repository);
        });
        $container->singleton(RoomHoursRepository::class);
        $container->singleton(RoomRulesService::class);
        $container->singleton(RoomService::class);
        $container->singleton(RoomVisibilityService::class);
        $container->singleton(RoomRequestValidator::class);
        $container->singleton(RoomController::class);
    }
}