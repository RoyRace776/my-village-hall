<?php
namespace MYVH\Core\Shortcode;

interface ShortcodeInterface
{
    public function tag(): string;

    public function render( mixed $atts = [], mixed $content = null): string;
}
