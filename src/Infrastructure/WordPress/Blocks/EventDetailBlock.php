<?php

declare(strict_types=1);

namespace MYVH\Infrastructure\WordPress\Blocks;

use MYVH\Application\Services\EventDetailRenderer;

final class EventDetailBlock extends AbstractBlock {
    public function __construct(private EventDetailRenderer $renderer) {
    }

    public function getName(): string {
        return 'myvh/event-detail';
    }

    public function render( array $attributes = [] ): string {
        return $this->renderer->render( $attributes );
    }
}