<?php
namespace MYVH\Login;

use MYVH\Core\Shortcode\ShortcodeInterface;

class LoginShortcode implements ShortcodeInterface
{
    public function tag(): string
    {
        return 'myvh_login';
    }

    public function render( mixed $atts = [], mixed $content = null): string
    {
        $login_css_path = MYVH_PLUGIN_DIR . 'assets/css/login.css';
        $login_css_version = file_exists($login_css_path) ? (string) filemtime($login_css_path) : MYVH_VERSION;

        wp_enqueue_style(
            'myvh-login',
            MYVH_PLUGIN_URL . 'assets/css/login.css',
            [],
            $login_css_version,
            'all'
        );

        $atts = shortcode_atts([
            'room_id' => null,
            'mode' => 'both',
            'login_page_url' => '',
            'register_page_url' => '',
        ], $atts);

        ob_start();

        include MYVH_PLUGIN_DIR . 'templates/Login/login.php';

        return ob_get_clean();
    }
}
