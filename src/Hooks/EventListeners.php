<?php

namespace MYVH\Hooks;

use MYVH\Bootstrap\Installer;
use MYVH\Network\SiteSeeder;
use MYVH\Portal\ClientAdminService;
use MYVH\Network\SiteProvisioningRepository;
use wpdb;

class EventListeners {
    public function __construct(
        private wpdb $wpdb,
        private SiteSeeder $site_seeder,
        private ClientAdminService $client_admin_service,
        private SiteProvisioningRepository $site_provisioning_repository
    ) {
    }

    public function register(): void {
        add_action( 'myvh_event_site.clone_failed', [ $this, 'handle_clone_failed' ] );
        add_action( 'myvh_site_cloned', [ $this, 'handle_site_cloned' ], 10, 2 );
        add_action( 'wp_delete_site', [ $this, 'handle_site_deleted' ], 10, 1 );
    }

    public function handle_clone_failed( array $data ): void {
        if ( empty( $data['provision_id'] ) ) {
            return;
        }

        $this->site_provisioning_repository->update_status(
            $data['provision_id'],
            'failed',
            [ 'error' => $data['reason'] ?? '' ]
        );
    }

    public function handle_site_cloned( mixed $blog_id, mixed $context ): void {
        $this->site_seeder->seed( $blog_id, $context );
    }

    public function handle_site_deleted( mixed $site ): void {
        Installer::drop_tables( $this->wpdb );

        $site_id = is_object( $site ) ? $site->blog_id : (int) $site;

        $this->client_admin_service->remove_all_assignments_for_blog( $site_id );

        $records = $this->site_provisioning_repository->get_by_site_id( $site_id );
        if ( ! $records ) {
            return;
        }

        foreach ( $records as $record ) {
            if ( ! isset( $record->id ) || null === $record->id ) {
                continue;
            }

            $this->site_provisioning_repository->delete( $record->id );
        }
    }
}
