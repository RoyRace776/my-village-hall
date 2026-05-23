<?php

namespace MYVH\Lifecycle;

use MYVH\Lifecycle\Contracts\CapabilityManagerInterface;
use MYVH\Lifecycle\Contracts\OptionStoreInterface;
use MYVH\Lifecycle\Contracts\PluginInstallerInterface;
use MYVH\Lifecycle\Contracts\RewriteRulesInterface;

class ActivationService {
    private const VERSION_OPTION = 'myvh_version';

    public function __construct(
        private PluginInstallerInterface $installer,
        private OptionStoreInterface $option_store,
        private CapabilityManagerInterface $capability_manager,
        private RewriteRulesInterface $rewrite_rules
    ) {
    }

    public function activate_site(): void {
        $this->grant_admin_capability();

        $this->installer->run();
        $this->option_store->set( self::VERSION_OPTION, MYVH_VERSION );
        $this->rewrite_rules->flush();
    }

    public function install_site(): void {
        $this->installer->run();
        $this->option_store->set( self::VERSION_OPTION, MYVH_VERSION );
    }

    private function grant_admin_capability(): void {
        $this->capability_manager->grant_to_role( 'administrator', 'manage_myvh' );
    }
}
