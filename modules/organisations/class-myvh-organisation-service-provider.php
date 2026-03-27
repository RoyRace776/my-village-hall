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

class Organisation_Service_Provider {

    public function register($container): void {

        // ── Repositories ──────────────────────────────────────────────────────
        $container->singleton( Organisation_Repository::class );
        $container->singleton( Organisation_Type_Repository::class );
        $container->singleton( Organisation_Member_Repository::class );
        $container->singleton( Organisation_Member_Request_Repository::class );

        // ── Services ──────────────────────────────────────────────────────────
        $container->singleton( Organisation_Service::class );
        $container->singleton( Organisation_Type_Service::class );
        $container->singleton( Organisation_Request_Validator::class );
        $container->singleton( Organisation_Type_Request_Validator::class );

        // ── Controllers ───────────────────────────────────────────────────────
        $container->singleton( Organisation_Controller::class );
        $container->singleton( Organisation_Type_Controller::class );
    }
}
