<?php
/**
 * Loader for password reset handler and shortcode
 */
namespace MYVH\Shortcodes;

class Password_Reset_Loader {
    public function init() {
        // Register shortcode
        add_shortcode('myvh_password_reset', [$this, 'render_shortcode']);
        // Init handler
        $handler = new \Password_Reset_Handler();
        $handler->init();
        $handler->hook_invalidate_on_change();
    }
    public function render_shortcode($atts = [], $content = null) {
        $shortcode = new Password_Reset_Shortcode();
        return $shortcode->render($atts, $content);
    }
}
