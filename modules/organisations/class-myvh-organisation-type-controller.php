<?php
/**
 * Controller for Organisation Type admin-post actions
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class Organisation_Type_Controller {

    private $service;
    private $request_validator;

    public function __construct(
        Organisation_Type_Service $service,
        Organisation_Type_Request_Validator $request_validator
    ) {
        $this->service = $service;
        $this->request_validator = $request_validator;
    }

    public function save(): void {
        if ( !current_user_can( 'manage_myvh' ) ) {
            wp_die( __( 'Permission denied', 'my-village-hall' ) );
        }

        check_admin_referer( 'myvh_save_org_type' );

        $data = Save_Organisation_Type_Request::from_post( wp_unslash( $_POST ) );

        $validation_result = $this->request_validator->validate( $data );
        if ( is_wp_error( $validation_result ) ) {
            wp_redirect( admin_url( 'admin.php?page=myvh-org-types&error=' . urlencode( $validation_result->get_error_message() ) ) );
            exit;
        }

        $result = $this->service->save( $data );

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

        $data = Delete_Organisation_Type_Request::from_query( $_GET );

        $validation_result = $this->request_validator->validate_delete( $data );
        if ( is_wp_error( $validation_result ) ) {
            wp_redirect( admin_url( 'admin.php?page=myvh-org-types&error=' . urlencode( $validation_result->get_error_message() ) ) );
            exit;
        }

        $this->service->delete( $data['id'] );

        wp_redirect( admin_url( 'admin.php?page=myvh-org-types&deleted=1' ) );
        exit;
    }
}
