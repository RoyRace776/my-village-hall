<?php

namespace MYVH\Lifecycle\WordPress;

use MYVH\Lifecycle\Contracts\OptionStoreInterface;

class WordPressOptionStore implements OptionStoreInterface {
    public function get( string $key, mixed $default = null ): mixed {
        return get_option( $key, $default );
    }

    public function set( string $key, mixed $value ): void {
        update_option( $key, $value );
    }
}
