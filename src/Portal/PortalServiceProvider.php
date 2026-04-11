<?php
namespace MYVH\Portal;

use MYVH\Portal\Actions\DeleteBookingAction;
use MYVH\Portal\Actions\GetBookingAction;
use MYVH\Portal\Actions\UpdateBookingAction;
use MYVH\Portal\Ajax\PortalBookingAjaxController;

/**
 * Service provider for the Portal domain
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}


class PortalServiceProvider {
    public function register($container): void {
        $container->singleton(PortalController::class);
        $container->singleton(PortalBootstrapDataService::class);
        $container->singleton(PortalShortcode::class);
        $container->singleton(GetBookingAction::class);
        $container->singleton(UpdateBookingAction::class);
        $container->singleton(DeleteBookingAction::class);
        $container->singleton(PortalBookingAjaxController::class);
    }
}
