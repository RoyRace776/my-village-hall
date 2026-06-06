<?php

declare(strict_types=1);

namespace MYVH\Pages\Content;

use MYVH\Pages\DTO\PageDefinition;

class ShortcodePageContentBuilder implements PageContentBuilderInterface
{
    public function build(PageDefinition $definition): string
    {
        return ( new BlockPageContentBuilder() )->build( $definition );
    }
}