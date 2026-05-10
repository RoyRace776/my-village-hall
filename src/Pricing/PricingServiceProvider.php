<?php
namespace MYVH\Pricing;

use MYVH\Container\Container;

class PricingServiceProvider
{
    public function register(Container $container): void {
        $container->singleton(DiscountRepository::class);
        $container->singleton(RoomRateRepository::class, function ($container) {
            $wpdb = $container->get(\wpdb::class);
            $repository = new RoomRateRepository($wpdb);

            return new CachedRoomRateRepository($repository);
        });
        $container->singleton(PricingService::class);
        $container->singleton(RoomRateService::class);
        $container->singleton(RoomRateRequestValidator::class);
        $container->singleton(RoomRateController::class);
    }
}