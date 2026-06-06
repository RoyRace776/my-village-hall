<?php

declare(strict_types=1);

namespace MYVH\Infrastructure\WordPress\Blocks;

use MYVH\Container\Container;

final class BlockRegistry {
    public function __construct(private Container $container) {
    }

    public function register(): void {
        $blocks = [
            $this->container->get( LoginBlock::class ),
            $this->container->get( PasswordResetBlock::class ),
            $this->container->get( PortalBlock::class ),
            $this->container->get( SubscriptionPlanTableBlock::class ),
            $this->container->get( CreateSiteBlock::class ),
            $this->container->get( CalendarBlock::class ),
            $this->container->get( PublicCalendarBlock::class ),
            $this->container->get( EventListBlock::class ),
            $this->container->get( EventDetailBlock::class ),
            $this->container->get( PortalCalendarBlock::class ),
        ];

        foreach ( $blocks as $block ) {
            if ( $block instanceof AbstractBlock ) {
                $block->register();
            }
        }
    }
}