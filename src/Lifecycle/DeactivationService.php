<?php

namespace MYVH\Lifecycle;

use MYVH\Lifecycle\Contracts\DeactivationPolicyInterface;
use MYVH\Lifecycle\Contracts\OvernightSchedulerInterface;
use MYVH\Lifecycle\Contracts\PluginInstallerInterface;
use MYVH\Lifecycle\Contracts\RewriteRulesInterface;

class DeactivationService {
    public function __construct(
        private PluginInstallerInterface $installer,
        private OvernightSchedulerInterface $overnight_scheduler,
        private DeactivationPolicyInterface $deactivation_policy,
        private RewriteRulesInterface $rewrite_rules
    ) {
    }

    public function deactivate_site(): void {
        $this->overnight_scheduler->clear_overdue_jobs();

        if ( $this->deactivation_policy->should_delete_on_deactivate() ) {
            $this->installer->tidy_up();
        }

        $this->rewrite_rules->flush();
    }
}
