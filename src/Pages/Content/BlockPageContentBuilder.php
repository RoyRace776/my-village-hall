<?php

declare(strict_types=1);

namespace MYVH\Pages\Content;

use MYVH\Pages\DTO\PageDefinition;

class BlockPageContentBuilder implements PageContentBuilderInterface
{
    public function build(PageDefinition $definition): string
    {
        $blockName  = $this->resolveBlockName($definition->shortcode);
        $attributes = $this->parseShortcodeAttributes($definition->shortcode);

        // Build raw Gutenberg markup (safe pattern)
        $raw = $this->buildHeading($definition->title)
             . "\n\n"
             . $this->buildDynamicBlock($blockName, $attributes);

        // Let WordPress normalize block structure
        $blocks = parse_blocks($raw);

        // Return fully valid serialized content
        return serialize_blocks($blocks);
    }

    /**
     * Build a valid core heading block
     */
    private function buildHeading(string $title): string
    {
        return sprintf(
            '<!-- wp:heading {"level":1} -->
<h1>%s</h1>
<!-- /wp:heading -->',
            esc_html($title)
        );
    }

    /**
     * Build a self-closing dynamic block
     */
    private function buildDynamicBlock(string $blockName, array $attrs): string
    {
        return sprintf(
            '<!-- wp:%s %s /-->',
            esc_attr($blockName),
            $this->encodeAttributes($attrs)
        );
    }

    /**
     * Safely encode block attributes for JSON usage
     */
    private function encodeAttributes(array $attrs): string
    {
        if (empty($attrs)) {
            return '';
        }

        $json = wp_json_encode($attrs);

        // Extra safety: ensure valid JSON string output
        return is_string($json) ? $json : '';
    }

    /**
     * Convert shortcode name → block name
     */
    private function resolveBlockName(string $shortcode): string
    {
        $shortcode = trim($shortcode);

        if ($shortcode === '') {
            return 'myvh/unknown';
        }

        $tag = strtok($shortcode, " \t\n\r\0\x0B");

        if (!is_string($tag) || $tag === '') {
            return 'myvh/unknown';
        }

        // Strip prefix if present
        if (str_starts_with($tag, 'myvh_')) {
            $tag = substr($tag, 5);
        }

        // Convert to block naming convention
        return 'myvh/' . str_replace('_', '-', $tag);
    }

    /**
     * Convert shortcode attributes → block attributes
     *
     * Example:
     * "myvh_login mode=\"login\"" → ['mode' => 'login']
     *
     * @return array<string, mixed>
     */
    private function parseShortcodeAttributes(string $shortcode): array
    {
        $parts = preg_split('/\s+/', trim($shortcode), 2);

        $attributeString = is_array($parts) && isset($parts[1])
            ? trim((string) $parts[1])
            : '';

        if ($attributeString === '') {
            return [];
        }

        $attributes = shortcode_parse_atts($attributeString);

        return is_array($attributes) ? $attributes : [];
    }
}