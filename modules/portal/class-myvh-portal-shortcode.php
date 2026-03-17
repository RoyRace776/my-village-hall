<?php

class MYVH_Portal_Shortcode {

    public function register() {
        add_shortcode('myvh_portal', [$this, 'render']);
    }

    public function render() {

        if (!is_user_logged_in()) {
            return do_shortcode('[myvh_login]');
        }

        ob_start();
        include MYVH_PLUGIN_DIR . 'modules/portal/templates/portal-shell.php';
        return ob_get_clean();
    }
}
