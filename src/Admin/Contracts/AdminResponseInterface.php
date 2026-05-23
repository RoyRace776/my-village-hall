<?php

namespace MYVH\Admin\Contracts;

interface AdminResponseInterface {
    /**
     * @param array<string, scalar> $query_args
     */
    public function redirect_to_admin_page( array $query_args ): void;
}
