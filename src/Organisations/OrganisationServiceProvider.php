<?php
namespace MYVH\Organisations;

use MYVH\Container\Container;

if (!defined('ABSPATH')) exit;

class OrganisationServiceProvider {
    public function register(Container $container): void {
        $container->singleton(OrganisationRepository::class, function ($container) {
            $wpdb = $container->get(\wpdb::class);
            $repository = new OrganisationRepository($wpdb);

            return new CachedOrganisationRepository($repository);
        });
        $container->singleton(OrganisationTypeRepository::class, function ($container) {
            $wpdb = $container->get(\wpdb::class);
            $repository = new OrganisationTypeRepository($wpdb);

            return new CachedOrganisationTypeRepository($repository);
        });
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
