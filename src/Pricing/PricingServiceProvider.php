<?php
namespace MYVH\Pricing;

class PricingServiceProvider
{
    public function register($container): void {
        $container->singleton(DiscountRepository::class);
        $container->singleton(RoomRateRepository::class);
        $container->singleton(PricingService::class);
        $container->singleton(RoomRateService::class);
        $container->singleton(RoomRateRequestValidator::class);
        $container->singleton(RoomRateController::class);
    }
}