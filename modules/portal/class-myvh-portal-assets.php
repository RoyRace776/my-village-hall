<?php

class MYVH_Portal_Assets {

    public static function enqueue() {

        wp_enqueue_style(
            'myvh-portal-css',
            MYVH_PLUGIN_URL . 'assets/css/portal.css',
            [],
            '1.0'
        );

        $fonts_url = apply_filters(
            'myvh_portal_fonts_url',
            'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=DM+Sans:wght@300;400;500&display=swap'
        );

        if ( $fonts_url ) {
            wp_enqueue_style( 'myvh-google-fonts', $fonts_url, [], null );
        }

        wp_enqueue_style(
            'myvh-dashboard',
            MYVH_PLUGIN_URL . 'assets/css/dashboard.css',
            $fonts_url ? [ 'myvh-google-fonts' ] : [],
            '1.0'
        );

        wp_enqueue_script(
            'myvh-portal-js',
            MYVH_PLUGIN_URL . 'assets/js/portal-app.js',
            [],
            '1.0',
            true
        );

        wp_localize_script(
            'myvh-portal-js',
            'myvhPortal',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
            ]
        );
    }
}
