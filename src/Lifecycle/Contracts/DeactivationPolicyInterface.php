<?php

namespace MYVH\Lifecycle\Contracts;

interface DeactivationPolicyInterface {
    public function should_delete_on_deactivate(): bool;
}
