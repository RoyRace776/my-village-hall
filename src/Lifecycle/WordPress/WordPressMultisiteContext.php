<?php

namespace MYVH\Lifecycle\WordPress;

use MYVH\Lifecycle\Contracts\MultisiteContextInterface;

class WordPressMultisiteContext implements MultisiteContextInterface {
    public function is_multisite(): bool {
        return is_multisite();
    }

    public function get_sites(): array {
        $sites = get_sites( [ 'number' => 0, 'count' => false ] );

        return is_array( $sites ) ? $sites : [];
    }

    public function switch_to_blog( int $blog_id ): void {
        switch_to_blog( $blog_id );
    }

    public function restore_current_blog(): void {
        restore_current_blog();
    }

    public function get_active_sitewide_plugins(): array {
        $active = get_site_option( 'active_sitewide_plugins', [] );

        return is_array( $active ) ? $active : [];
    }
}
