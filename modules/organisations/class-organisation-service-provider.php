<?php
/**
 * Service provider for the Organisations domain
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class MYVH_Organisation_Service_Provider {

    public function register($container): void {
        global $wpdb;

        // ── Repositories ──────────────────────────────────────────────────────
        $container->singleton( MYVH_Organisation_Repository::class );
        $container->singleton( MYVH_Organisation_Type_Repository::class );
        $container->singleton( MYVH_Organisation_Member_Repository::class );

        // ── Services ──────────────────────────────────────────────────────────
        $container->singleton( MYVH_Organisation_Service::class );
        $container->singleton( MYVH_Organisation_Type_Service::class );

        // ── Controllers ───────────────────────────────────────────────────────
        $container->singleton( MYVH_Organisation_Controller::class );
        $container->singleton( MYVH_Organisation_Type_Controller::class );
    }
}
