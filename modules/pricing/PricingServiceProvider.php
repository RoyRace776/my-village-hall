<?php

require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-save-room-rate-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-myvh-room-rate-request-validator.php';

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