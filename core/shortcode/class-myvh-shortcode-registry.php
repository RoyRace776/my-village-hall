<?php

namespace MYVH\Infrastructure;

class ShortcodeRegistry
{
    private $shortcodes = [];

    public function add($shortcode): void {
        $this->shortcodes[] = $shortcode;
    }

    public function register(): void {
        foreach ($this->shortcodes as $shortcode) {
            add_shortcode(
                $shortcode->tag(),
                [$shortcode, 'render']
            );
        }
    }
}
