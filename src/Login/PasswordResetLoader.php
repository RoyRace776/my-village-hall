<?php
/**
 * Loader for password reset handler and shortcode
 */
namespace MYVH\Login;

use MYVH\Container\Container;

class PasswordResetLoader {
    public function init() {
        // Register shortcode
        add_shortcode('myvh_password_reset', [$this, 'render_shortcode']);
        // Init handler
        global $myvh_container;

        $handler = $myvh_container instanceof Container
            ? $myvh_container->get(PasswordResetHandler::class)
            : new PasswordResetHandler();

        $handler->init();
        $handler->hook_invalidate_on_change();
    }
    public function render_shortcode($atts = [], $content = null) {
        $shortcode = new PasswordResetShortcode();
        return $shortcode->render($atts, $content);
    }
}
