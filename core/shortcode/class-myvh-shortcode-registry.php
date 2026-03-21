<?php

namespace MYVH\Infrastructure;

class MYVH_Shortcode_Registry
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
