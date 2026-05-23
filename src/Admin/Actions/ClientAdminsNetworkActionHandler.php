<?php

namespace MYVH\Admin\Actions;

use MYVH\Admin\Contracts\AdminRequestInterface;
use MYVH\Admin\Contracts\AdminResponseInterface;
use MYVH\Admin\Contracts\ClientAdminActionHandlerInterface;
use MYVH\Portal\ClientAdminService;
use WP_User;

class ClientAdminsNetworkActionHandler implements ClientAdminActionHandlerInterface {
    public function __construct(
        private AdminRequestInterface $request,
        private AdminResponseInterface $response,
        private ClientAdminService $service
    ) {
    }

    public function handle(): bool {
        $action = $this->request->post_string( 'myvh_client_admin_action' );
        if ( 'POST' !== $this->request->method() || '' === $action ) {
            return false;
        }

        $this->request->check_nonce( 'myvh_site_client_admins' );

        $redirect_args = [ 'page' => 'myvh-client-admins-network' ];
        $blog_id = get_current_blog_id();

        if ( 'add' === $action ) {
            $identifier = $this->request->post_string( 'user_identifier' );

            if ( '' === $identifier ) {
                $redirect_args['myvh_notice'] = 'missing_user';
            } else {
                $user = $this->service->find_user( $identifier );
                if ( $user instanceof WP_User ) {
                    $this->service->add_assignment( $blog_id, (int) $user->ID );
                    $redirect_args['myvh_notice'] = 'added';
                } else {
                    $redirect_args['myvh_notice'] = 'user_not_found';
                }
            }
        } elseif ( 'remove' === $action ) {
            $user_id = $this->request->post_int( 'user_id' );

            if ( $user_id > 0 ) {
                $this->service->remove_assignment( $blog_id, $user_id );
                $redirect_args['myvh_notice'] = 'removed';
            } else {
                $redirect_args['myvh_notice'] = 'invalid_user';
            }
        }

        $this->response->redirect_to_admin_page( $redirect_args );

        return true;
    }
}
