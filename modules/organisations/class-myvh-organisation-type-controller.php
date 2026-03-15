<?php
/**
 * Controller for Organisation Type admin-post actions
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class MYVH_Organisation_Type_Controller {

    private $service;

    public function __construct( MYVH_Organisation_Type_Service $service ) {
        $this->service = $service;
    }

    public function save(): void {
        if ( !current_user_can( 'manage_myvh' ) ) {
            wp_die( __( 'Permission denied', 'my-village-hall' ) );
        }

        check_admin_referer( 'myvh_save_org_type' );

        $result = $this->service->save( $_POST );

        if ( is_wp_error( $result ) ) {
            wp_redirect( admin_url( 'admin.php?page=myvh-org-types&error=' . urlencode( $result->get_error_message() ) ) );
            exit;
        }

        wp_redirect( admin_url( 'admin.php?page=myvh-org-types&updated=1' ) );
        exit;
    }

    public function delete(): void {
        if ( !current_user_can( 'manage_myvh' ) ) {
            wp_die( __( 'Permission denied', 'my-village-hall' ) );
        }

        check_admin_referer( 'myvh_delete_org_type' );

        $this->service->delete( intval( $_GET['id'] ) );

        wp_redirect( admin_url( 'admin.php?page=myvh-org-types&deleted=1' ) );
        exit;
    }
}
