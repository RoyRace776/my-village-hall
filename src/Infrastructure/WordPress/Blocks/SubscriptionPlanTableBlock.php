<?php

declare(strict_types=1);

namespace MYVH\Infrastructure\WordPress\Blocks;

use MYVH\Infrastructure\WordPress\Blocks\AbstractBlock;
use MYVH\Application\Services\SubscriptionPlanTableRenderer;

final class SubscriptionPlanTableBlock extends AbstractBlock {
    public function __construct(private \MYVH\Application\Services\SubscriptionPlanTableRenderer $renderer) {
    }

    public function getName(): string {
        return 'myvh/subscription-plan-table';
    }

    public function render( array $attributes = [] ): string {
        return $this->renderer->render( $attributes );
    }
}