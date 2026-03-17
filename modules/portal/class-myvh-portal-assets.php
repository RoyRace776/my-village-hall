<?php

class MYVH_Portal_Assets {

    public static function enqueue() {

        wp_enqueue_style(
            'myvh-portal-css',
            MYVH_PLUGIN_URL . 'modules/portal/assets/portal.css',
            [],
            '1.0'
        );

        wp_enqueue_script(
            'myvh-portal-js',
            MYVH_PLUGIN_URL . 'modules/portal/assets/portal-app.js',
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
