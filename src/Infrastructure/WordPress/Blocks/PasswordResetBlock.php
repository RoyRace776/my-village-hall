<?php

declare(strict_types=1);

namespace MYVH\Infrastructure\WordPress\Blocks;

use MYVH\Application\Services\PasswordResetRenderer;

final class PasswordResetBlock extends AbstractBlock {
    public function __construct(private PasswordResetRenderer $renderer) {
    }

    public function getName(): string {
        return 'myvh/password-reset';
    }

    public function render( array $attributes = [] ): string {
        return $this->renderer->render( $attributes );
    }
}