<?php
namespace MYVH\Organisations;

if (!defined('ABSPATH')) exit;

class OrganisationServiceProvider {
    public function register($container): void {
        $container->singleton(OrganisationRepository::class);
        $container->singleton(OrganisationTypeRepository::class);
        $container->singleton(OrganisationMemberRepository::class);
        $container->singleton(OrganisationMemberRequestRepository::class);
        $container->singleton(OrganisationService::class);
        $container->singleton(OrganisationTypeService::class);
        $container->singleton(OrganisationRequestValidator::class);
        $container->singleton(OrganisationTypeRequestValidator::class);
        $container->singleton(OrganisationController::class);
        $container->singleton(OrganisationTypeController::class);
    }
}
