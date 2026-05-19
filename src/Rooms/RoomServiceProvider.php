<?php
namespace MYVH\Rooms;

use MYVH\Container\Container;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RoomServiceProvider
{
    public function register(Container $container): void {
        $container->singleton(RoomRepository::class, function ($container) {
            $wpdb = $container->get(\wpdb::class);
            try {
                $logger = $container->get(LoggerInterface::class);
            } catch (\Exception $e) {
                $logger = new NullLogger();
            }
            $repository = new RoomRepository(
                $wpdb,
                $logger
            );

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