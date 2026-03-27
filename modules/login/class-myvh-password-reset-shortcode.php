<?php
namespace MYVH\Shortcodes;
/**
 * Password_Reset_Shortcode: Renders password reset forms via shortcode
 */
class Password_Reset_Shortcode implements Shortcode_Interface {
    public function tag(): string { return 'myvh_password_reset'; }
    public function render($atts = [], $content = null): string {
        // If reset link params present, show confirm form; else show request form
        $is_confirm = !empty($_GET['myvh_reset']) && !empty($_GET['uid']) && !empty($_GET['token']);
        ob_start();
        if ($is_confirm) {
            include MYVH_PLUGIN_DIR . 'modules/login/password-reset-confirm-form.php';
        } else {
            include MYVH_PLUGIN_DIR . 'modules/login/password-reset-form.php';
        }
        return ob_get_clean();
    }
}
