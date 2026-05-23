<?php

namespace MYVH\Admin\Contracts;

interface AdminRequestInterface {
    public function method(): string;

    public function post_string( string $key, string $default = '' ): string;

    public function post_int( string $key, int $default = 0 ): int;

    public function query_string( string $key, string $default = '' ): string;

    public function check_nonce( string $action ): void;
}
