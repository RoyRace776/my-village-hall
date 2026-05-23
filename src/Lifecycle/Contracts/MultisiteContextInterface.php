<?php

namespace MYVH\Lifecycle\Contracts;

interface MultisiteContextInterface {
    public function is_multisite(): bool;

    /** @return array<int, object> */
    public function get_sites(): array;

    public function switch_to_blog( int $blog_id ): void;

    public function restore_current_blog(): void;

    /** @return array<string, mixed> */
    public function get_active_sitewide_plugins(): array;
}
