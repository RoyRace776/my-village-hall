<?php

declare(strict_types=1);

namespace MYVH\Application\Services;

final class PasswordResetRenderer {
    public function render( array $attributes = [] ): string {
        $is_confirm = ! empty( $_GET['myvh_reset'] ) && ! empty( $_GET['uid'] ) && ! empty( $_GET['token'] );

        ob_start();
        if ( $is_confirm ) {
            include MYVH_PLUGIN_DIR . 'templates/Login/password-reset-confirm-form.php';
        } else {
            include MYVH_PLUGIN_DIR . 'templates/Login/password-reset-form.php';
        }

        return (string) ob_get_clean();
    }
}