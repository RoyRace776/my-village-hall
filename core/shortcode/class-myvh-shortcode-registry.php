<?php

namespace MYVH\Infrastructure;

class MYVH_Shortcode_Registry
{
    private $shortcodes = [];

    public function add($shortcode)
    {
        $this->shortcodes[] = $shortcode;
    }

    public function register()
    {
        foreach ($this->shortcodes as $shortcode) {
            add_shortcode(
                $shortcode->tag(),
                [$shortcode, 'render']
            );
        }
    }
}
