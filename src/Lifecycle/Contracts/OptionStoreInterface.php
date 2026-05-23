<?php

namespace MYVH\Lifecycle\Contracts;

interface OptionStoreInterface {
    public function get( string $key, mixed $default = null ): mixed;

    public function set( string $key, mixed $value ): void;
}
