<?php
/**
 * Loader for password reset handler and shortcode
 */
namespace MYVH\Shortcodes;

class PasswordResetLoader {
    public function init() {
        // Register shortcode
        add_shortcode('myvh_password_reset', [$this, 'render_shortcode']);
        // Init handler
        $handler = new \PasswordResetHandler();
        $handler->init();
        $handler->hook_invalidate_on_change();
    }
    public function render_shortcode($atts = [], $content = null) {
        $shortcode = new PasswordResetShortcode();
        return $shortcode->render($atts, $content);
    }
}
