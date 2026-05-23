<?php

namespace MYVH\Admin\WordPress;

use MYVH\Admin\Contracts\AdminRequestInterface;

class WordPressAdminRequest implements AdminRequestInterface {
    public function method(): string {
        return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) );
    }

    public function post_string( string $key, string $default = '' ): string {
        if ( ! isset( $_POST[ $key ] ) ) {
            return $default;
        }

        return sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) );
    }

    public function post_int( string $key, int $default = 0 ): int {
        if ( ! isset( $_POST[ $key ] ) ) {
            return $default;
        }

        return (int) wp_unslash( $_POST[ $key ] );
    }

    public function query_string( string $key, string $default = '' ): string {
        if ( ! isset( $_GET[ $key ] ) ) {
            return $default;
        }

        return sanitize_key( wp_unslash( (string) $_GET[ $key ] ) );
    }

    public function check_nonce( string $action ): void {
        check_admin_referer( $action );
    }
}
