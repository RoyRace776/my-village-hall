<?php

declare(strict_types=1);

namespace MYVH\Pages\Support;

final class PageContentMatcher {
    public static function containsFeature( string $content, string $shortcode_tag, ?string $block_name = null ): bool {
        if ( $content === '' ) {
            return false;
        }

        if ( $shortcode_tag !== '' && has_shortcode( $content, $shortcode_tag ) ) {
            return true;
        }

        if ( $block_name !== null && $block_name !== '' && function_exists( 'has_block' ) && has_block( $block_name, $content ) ) {
            return true;
        }

        return false;
    }
}