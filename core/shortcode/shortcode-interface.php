<?php
namespace MYVH\Shortcodes;

interface ShortcodeInterface
{
    public function tag(): string;

    public function render($atts = [], $content = null): string;
}
