<?php

declare(strict_types=1);

namespace MYVH\Infrastructure\WordPress\Blocks;

use MYVH\Infrastructure\WordPress\Blocks\AbstractBlock;
use MYVH\Application\Services\CreateSiteRenderer;

final class CreateSiteBlock extends AbstractBlock {
    public function __construct(private CreateSiteRenderer $renderer) {
    }

    public function getName(): string {
        return 'myvh/create-site';
    }

    public function render( array $attributes = [] ): string {
        return $this->renderer->render( $attributes );
    }
}