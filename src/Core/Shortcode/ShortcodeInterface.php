<?php
namespace MYVH\Core\Shortcode;

interface ShortcodeInterface
{
    public function tag(): string;

    public function render($atts = [], $content = null): string;
}