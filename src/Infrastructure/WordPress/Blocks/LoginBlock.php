<?php

declare(strict_types=1);

namespace MYVH\Infrastructure\WordPress\Blocks;

use MYVH\Application\Services\LoginRenderer;

final class LoginBlock extends AbstractBlock {
    public function __construct(private LoginRenderer $renderer) {
    }

    public function getName(): string {
        return 'myvh/login';
    }

    public function render( array $attributes = [] ): string {
        return $this->renderer->render( $attributes );
    }
}