<?php

namespace MYVH\Lifecycle\Contracts;

interface PluginInstallerInterface {
    public function run(): void;

    public function maybe_upgrade(): void;

    public function tidy_up(): void;
}
