<?php

declare(strict_types=1);

namespace MYVH\Infrastructure\WordPress\Blocks;

use MYVH\Application\Services\PortalRenderer;

final class PortalBlock extends AbstractBlock {
    public function __construct(private PortalRenderer $renderer) {
    }

    public function getName(): string {
        return 'myvh/portal';
    }

    public function render( array $attributes = [] ): string {
        return $this->renderer->render( $attributes );
    }
}