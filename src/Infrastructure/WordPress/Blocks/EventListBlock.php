<?php

declare(strict_types=1);

namespace MYVH\Infrastructure\WordPress\Blocks;

use MYVH\Application\Services\EventListRenderer;

final class EventListBlock extends AbstractBlock {
    public function __construct(private EventListRenderer $renderer) {
    }

    public function getName(): string {
        return 'myvh/event-list';
    }

    public function render( array $attributes = [] ): string {
        return $this->renderer->render( $attributes );
    }
}