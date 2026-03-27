<?php
namespace MYVH\Shortcodes;

interface Shortcode_Interface
{
    public function tag(): string;

    public function render($atts = [], $content = null): string;
}
