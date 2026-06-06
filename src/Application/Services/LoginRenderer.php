<?php

declare(strict_types=1);

namespace MYVH\Application\Services;

final class LoginRenderer {
    public function render( array $attributes = [] ): string {
        $login_css_path = MYVH_PLUGIN_DIR . 'assets/css/login.css';
        $login_css_version = file_exists( $login_css_path ) ? (string) filemtime( $login_css_path ) : MYVH_VERSION;

        wp_enqueue_style(
            'myvh-login',
            MYVH_PLUGIN_URL . 'assets/css/login.css',
            [],
            $login_css_version,
            'all'
        );

        $attributes = shortcode_atts(
            [
                'room_id' => null,
                'mode' => 'both',
                'login_page_url' => '',
                'register_page_url' => '',
            ],
            $attributes
        );

        ob_start();
        include MYVH_PLUGIN_DIR . 'templates/Login/login.php';

        return (string) ob_get_clean();
    }
}