<?php
/**
 * Controller for Organisation and Organisation Member admin-post actions
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class MYVH_Organisation_Controller {

    private $service;

    public function __construct( MYVH_Organisation_Service $service ) {
        $this->service = $service;
    }

    public function save(): void {
        if ( !current_user_can( 'manage_myvh' ) ) {
            wp_die( __( 'Permission denied', 'my-village-hall' ) );
        }

        check_admin_referer( 'myvh_save_organisation' );

        $result = $this->service->save( $_POST );

        if ( is_wp_error( $result ) ) {
            wp_redirect( admin_url( 'admin.php?page=myvh-organisations&error=' . urlencode( $result->get_error_message() ) ) );
            exit;
        }

        wp_redirect( admin_url( 'admin.php?page=myvh-organisations&updated=1' ) );
        exit;
    }

    public function delete(): void {
        if ( !current_user_can( 'manage_myvh' ) ) {
            wp_die( __( 'Permission denied', 'my-village-hall' ) );
        }

        check_admin_referer( 'myvh_delete_organisation' );

        $this->service->delete( intval( $_GET['id'] ) );

        wp_redirect( admin_url( 'admin.php?page=myvh-organisations&deleted=1' ) );
        exit;
    }

    public function add_member(): void {
        if ( !current_user_can( 'manage_myvh' ) ) {
            wp_die( __( 'Permission denied', 'my-village-hall' ) );
        }

        check_admin_referer( 'myvh_add_org_member' );

        $org_id  = intval( $_POST['organisation_id'] );
        $user_id = intval( $_POST['user_id'] );

        $result = $this->service->add_member( $org_id, $user_id );

        // Support redirect back to the customer edit page
        if ( !empty( $_POST['redirect'] ) && $_POST['redirect'] === 'customer' && !empty( $_POST['customer_id'] ) ) {
            $back = admin_url( 'admin.php?page=myvh-customers&edit=' . intval( $_POST['customer_id'] ) );
            if ( is_wp_error( $result ) ) {
                $back .= '&error=' . urlencode( $result->get_error_message() );
            } else {
                $back .= '&updated=1';
            }
            wp_redirect( $back );
            exit;
        }

        if ( is_wp_error( $result ) ) {
            wp_redirect( admin_url( 'admin.php?page=myvh-org-members&organisation_id=' . $org_id . '&error=' . urlencode( $result->get_error_message() ) ) );
            exit;
        }

        wp_redirect( admin_url( 'admin.php?page=myvh-org-members&organisation_id=' . $org_id . '&updated=1' ) );
        exit;
    }

    public function remove_member(): void {
        if ( !current_user_can( 'manage_myvh' ) ) {
            wp_die( __( 'Permission denied', 'my-village-hall' ) );
        }

        check_admin_referer( 'myvh_remove_org_member' );

        $member_id = intval( $_GET['id'] );
        $org_id    = intval( $_GET['organisation_id'] ?? 0 );

        $this->service->remove_member( $member_id );

        // Support redirect back to the customer edit page
        if ( !empty( $_GET['redirect'] ) && $_GET['redirect'] === 'customer' && !empty( $_GET['customer_id'] ) ) {
            wp_redirect( admin_url( 'admin.php?page=myvh-customers&edit=' . intval( $_GET['customer_id'] ) . '&updated=1' ) );
            exit;
        }

        wp_redirect( admin_url( 'admin.php?page=myvh-org-members&organisation_id=' . $org_id . '&deleted=1' ) );
        exit;
    }
}
