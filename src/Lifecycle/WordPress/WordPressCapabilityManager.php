<?php

namespace MYVH\Lifecycle\WordPress;

use MYVH\Lifecycle\Contracts\CapabilityManagerInterface;

class WordPressCapabilityManager implements CapabilityManagerInterface {
    public function grant_to_role( string $role_name, string $capability ): void {
        $role = get_role( $role_name );
        if ( $role && ! $role->has_cap( $capability ) ) {
            $role->add_cap( $capability );
        }
    }
}
