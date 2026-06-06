<?php

declare(strict_types=1);

namespace MYVH\Pages\Content;

use MYVH\Pages\DTO\PageDefinition;

interface PageContentBuilderInterface {
    public function build(PageDefinition $definition): string;
}