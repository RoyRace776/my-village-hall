<?php
namespace MYVH\Login;

use MYVH\Application\Services\LoginRenderer;
use MYVH\Core\Shortcode\ShortcodeInterface;

class LoginShortcode implements ShortcodeInterface
{
    public function tag(): string
    {
        return 'myvh_login';
    }

    public function render( mixed $atts = [], mixed $content = null): string
    {
        return ( new LoginRenderer() )->render( (array) $atts );
    }
}
