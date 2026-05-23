<?php

namespace MYVH\Admin\Contracts;

interface ClientAdminActionHandlerInterface {
    /**
     * Handles a request and returns true if a redirect response was emitted.
     */
    public function handle(): bool;
}
