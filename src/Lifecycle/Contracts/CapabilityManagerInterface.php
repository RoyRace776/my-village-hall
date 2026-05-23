<?php

namespace MYVH\Lifecycle\Contracts;

interface CapabilityManagerInterface {
    public function grant_to_role( string $role_name, string $capability ): void;
}
