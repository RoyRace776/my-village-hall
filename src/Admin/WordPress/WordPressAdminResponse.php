<?php

namespace MYVH\Admin\WordPress;

use MYVH\Admin\Contracts\AdminResponseInterface;

class WordPressAdminResponse implements AdminResponseInterface {
    public function redirect_to_admin_page( array $query_args ): void {
        wp_safe_redirect( add_query_arg( $query_args, admin_url( 'admin.php' ) ) );
        exit;
    }
}
