<?php

namespace MYVH\Lifecycle\WordPress;

use MYVH\Lifecycle\Contracts\DeactivationPolicyInterface;
use MYVH\Settings\GeneralSettings;

class GeneralSettingsDeactivationPolicy implements DeactivationPolicyInterface {
    public function should_delete_on_deactivate(): bool {
        $general_settings = new GeneralSettings();

        return (bool) $general_settings->get( 'delete_on_deactivate' );
    }
}
