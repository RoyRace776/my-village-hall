<?php

declare(strict_types=1);

namespace MYVH\Pages\Installer;

use MYVH\Pages\Services\PageSyncService;

class PageInstaller {
    public function __construct(private PageSyncService $sync_service) {
    }

    public function run(): void {
        $this->sync_service->syncAll();
    }
}
