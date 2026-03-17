<?php
namespace MYVH\Shortcodes;

class MYVH_Login_Shortcode implements MYVH_Shortcode_Interface
{
    public function tag(): string
    {
        return 'myvh_login';
    }

    public function render($atts = [], $content = null): string
    {
        wp_enqueue_script(
            'myvh-login',
            MYVH_PLUGIN_URL . 'module/portal/css/login.css',
            null,
            '1.0',
            true
        );

        $atts = shortcode_atts([
            'room_id' => null
        ], $atts);

        ob_start();

        include MYVH_PLUGIN_DIR . 'modules/portal/templates/login.php';

        return ob_get_clean();
    }
}
