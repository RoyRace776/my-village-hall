<?php

declare(strict_types=1);

namespace MYVH\Pages\DTO;

final class PageDefinition {
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $shortcode,
        public readonly bool $is_front_page = false
    ) {
    }
}
