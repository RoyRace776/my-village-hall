<?php

declare(strict_types=1);

namespace MYVH\Infrastructure\WordPress\Blocks;

use MYVH\Application\Services\CalendarRenderer;

final class CalendarBlock extends AbstractBlock {
    public function __construct(private CalendarRenderer $renderer) {
    }

    public function getName(): string {
        return 'myvh/calendar';
    }

    public function render( array $attributes = [] ): string {
        return $this->renderer->render( $attributes );
    }
}