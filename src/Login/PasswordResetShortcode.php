<?php
namespace MYVH\Login;

use MYVH\Application\Services\PasswordResetRenderer;
use MYVH\Core\Shortcode\ShortcodeInterface;
/**
 * PasswordResetShortcode: Renders password reset forms via shortcode
 */
class PasswordResetShortcode implements ShortcodeInterface {
    public function tag(): string { return 'myvh_password_reset'; }
    public function render( mixed $atts = [], mixed $content = null): string {
        return ( new PasswordResetRenderer() )->render( (array) $atts );
    }
}
