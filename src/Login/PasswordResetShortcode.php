<?php
namespace MYVH\Login;

use MYVH\Core\Shortcode\ShortcodeInterface;
/**
 * PasswordResetShortcode: Renders password reset forms via shortcode
 */
class PasswordResetShortcode implements ShortcodeInterface {
    public function tag(): string { return 'myvh_password_reset'; }
    public function render( mixed $atts = [], mixed $content = null): string {
        // If reset link params present, show confirm form; else show request form
        $is_confirm = !empty($_GET['myvh_reset']) && !empty($_GET['uid']) && !empty($_GET['token']);
        ob_start();
        if ($is_confirm) {
            include MYVH_PLUGIN_DIR . 'templates/Login/password-reset-confirm-form.php';
        } else {
            include MYVH_PLUGIN_DIR . 'templates/Login/password-reset-form.php';
        }
        return ob_get_clean();
    }
}
