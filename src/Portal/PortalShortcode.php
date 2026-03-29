<?php
namespace MYVH\Portal;

use MYVH\Shortcodes\ShortcodeInterface;

class PortalShortcode implements ShortcodeInterface
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

        \AssetLoader::enqueue_portal();

        \ob_start();
        include MYVH_PLUGIN_DIR . 'modules/portal/templates/portal-shell.php';
        return (string) \ob_get_clean();
    }
}
