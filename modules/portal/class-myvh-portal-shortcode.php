<?php
namespace MYVH\Shortcodes;

class Portal_Shortcode implements Shortcode_Interface
{
    public function tag(): string
    {
        return 'myvh_portal';
    }

    public function render($atts = [], $content = null): string
    {
        if ( ! \is_user_logged_in() ) {
            return \do_shortcode( '[myvh_login]' );
        }

        \Asset_Loader::enqueue_portal();

        \ob_start();
        include MYVH_PLUGIN_DIR . 'modules/portal/templates/portal-shell.php';
        return (string) \ob_get_clean();
    }
}
