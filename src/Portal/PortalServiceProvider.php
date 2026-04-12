<?php
namespace MYVH\Portal;

use MYVH\Portal\Actions\DeleteBookingAction;
use MYVH\Portal\Actions\GetBookingAction;
use MYVH\Portal\Actions\UpdateBookingAction;
use MYVH\Portal\Ajax\PortalAccountAjaxController;
use MYVH\Portal\Ajax\PortalAdminConfigAjaxController;
use MYVH\Portal\Ajax\PortalAdminConfigPageRenderer;
use MYVH\Portal\Ajax\PortalBillingAjaxController;
use MYVH\Portal\Ajax\PortalBookingAjaxController;
use MYVH\Portal\Ajax\PortalBillingPageRenderer;
use MYVH\Portal\Ajax\PortalEmailTemplateAjaxController;
use MYVH\Portal\Ajax\PortalOrganisationAjaxController;
use MYVH\Portal\Ajax\PortalOrganisationPageRenderer;
use MYVH\Portal\Ajax\PortalPageAjaxController;
use MYVH\Portal\Ajax\PortalPeoplePageRenderer;
use MYVH\Portal\Ajax\PortalPeopleAjaxController;

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
        $container->singleton(PortalAccountAjaxController::class);
        $container->singleton(PortalAdminConfigAjaxController::class);
        $container->singleton(PortalAdminConfigPageRenderer::class);
        $container->singleton(PortalBillingAjaxController::class);
        $container->singleton(PortalBillingPageRenderer::class);
        $container->singleton(PortalBookingAjaxController::class);
        $container->singleton(PortalEmailTemplateAjaxController::class);
        $container->singleton(PortalOrganisationAjaxController::class);
        $container->singleton(PortalOrganisationPageRenderer::class);
        $container->singleton(PortalPageAjaxController::class);
        $container->singleton(PortalPeoplePageRenderer::class);
        $container->singleton(PortalPeopleAjaxController::class);
    }
}
