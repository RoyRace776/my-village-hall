<?php
namespace MYVH\Portal;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MYVH\Core\Shortcode\ShortcodeInterface;
use MYVH\Core\Support\AssetLoader;

class PortalShortcode implements ShortcodeInterface
{
    public function tag(): string
    {
        return 'myvh_portal';
    }

    public function render($atts = [], $content = null): string
    {
        if ( ! is_user_logged_in() ) {
            return do_shortcode( '[myvh_login]' );
        }

        AssetLoader::enqueue_portal();

        ob_start();
        include MYVH_PLUGIN_DIR . 'templates/Portal/portal-shell.php';
        return (string) ob_get_clean();
    }
}
