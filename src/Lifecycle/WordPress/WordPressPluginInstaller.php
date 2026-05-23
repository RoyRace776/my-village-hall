<?php

namespace MYVH\Lifecycle\WordPress;

use MYVH\Bootstrap\Installer;
use MYVH\Lifecycle\Contracts\PluginInstallerInterface;

class WordPressPluginInstaller implements PluginInstallerInterface {
    public function run(): void {
        Installer::run();
    }

    public function maybe_upgrade(): void {
        Installer::maybe_upgrade();
    }

    public function tidy_up(): void {
        Installer::tidy_up();
    }
}
