<?php
/**
 * Service provider for the Organisations domain
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-save-organisation-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-delete-organisation-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-add-org-member-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-remove-org-member-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-organisation-request-validator.php';
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-save-organisation-type-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-delete-organisation-type-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/organisations/class-myvh-organisation-type-request-validator.php';

class OrganisationServiceProvider {

    public function register($container): void {

        // ── Repositories ──────────────────────────────────────────────────────
        $container->singleton( OrganisationRepository::class );
        $container->singleton( OrganisationTypeRepository::class );
        $container->singleton( OrganisationMemberRepository::class );
        $container->singleton( OrganisationMemberRequestRepository::class );

        // ── Services ──────────────────────────────────────────────────────────
        $container->singleton( OrganisationService::class );
        $container->singleton( OrganisationTypeService::class );
        $container->singleton( OrganisationRequestValidator::class );
        $container->singleton( OrganisationTypeRequestValidator::class );

        // ── Controllers ───────────────────────────────────────────────────────
        $container->singleton( OrganisationController::class );
        $container->singleton( OrganisationTypeController::class );
    }
}
