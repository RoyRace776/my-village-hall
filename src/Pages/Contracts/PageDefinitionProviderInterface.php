<?php

declare(strict_types=1);

namespace MYVH\Pages\Contracts;

interface PageDefinitionProviderInterface {
    /**
     * @return array<\MYVH\Pages\DTO\PageDefinition>
     */
    public function getPages(): array;
}
