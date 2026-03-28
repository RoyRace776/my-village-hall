<?php

require_once MYVH_PLUGIN_DIR . 'modules/addons/SaveAddonRequest.php';
require_once MYVH_PLUGIN_DIR . 'modules/addons/AddonRequestValidator.php';

class AddonServiceProvider
{
    public function register($container): void {
        $container->singleton(AddonRepository::class);
        $container->singleton(AddonService::class);
        $container->singleton(AddonRequestValidator::class);
        $container->singleton(AddonController::class);
    }
}