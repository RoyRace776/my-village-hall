<?php

declare(strict_types=1);

namespace MYVH\Infrastructure\WordPress\Blocks;

abstract class AbstractBlock {
    abstract public function getName(): string;

    abstract public function render( array $attributes = [] ): string;

    public function register(): void {
        $this->registerAssets();

        register_block_type(
            $this->getName(),
            [
                'editor_script' => $this->getScriptHandle(),
                'render_callback' => [ $this, 'render' ],
            ]
        );
    }

    protected function registerAssets(): void {
        wp_register_script(
            $this->getScriptHandle(),
            MYVH_PLUGIN_URL . 'assets/js/' . $this->getScriptFileName(),
            [ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components' ],
            MYVH_VERSION,
            true
        );
    }

    protected function getScriptHandle(): string {
        return 'myvh-block-' . str_replace( '/', '-', $this->getName() );
    }

    protected function getScriptFileName(): string {
        return str_replace( '/', '-', $this->getName() ) . '.js';
    }
}