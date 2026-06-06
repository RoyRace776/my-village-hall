<?php

declare(strict_types=1);

namespace MYVH\Pages\Support;

use WP_Post;

class PageGuard {
    private const MANAGED_META_KEY = '_myvh_managed';

    public function register(): void {
        add_filter('pre_delete_post', [ $this, 'preventDelete' ], 10, 2);
        add_filter('pre_trash_post', [ $this, 'preventTrash' ], 10, 2);
        add_action('wp_trash_post', [ $this, 'preventTrash' ], 10, 1);
    }

    public function preventDelete(mixed $delete, mixed $post): mixed {
        if (! $post instanceof WP_Post || $post->post_type !== 'page') {
            return $delete;
        }

        return $this->isManaged((int) $post->ID) ? false : $delete;
    }

    public function preventTrash(mixed $post_id, mixed $post = null): mixed {
        if ($post instanceof WP_Post) {
            $post_id = (int) $post->ID;
        }

        if (! is_numeric($post_id)) {
            return $post_id;
        }

        $id = (int) $post_id;
        if ($id <= 0 || get_post_type($id) !== 'page') {
            return $post_id;
        }

        return $this->isManaged($id) ? false : $post_id;
    }

    private function isManaged(int $post_id): bool {
        return get_post_meta($post_id, self::MANAGED_META_KEY, true) === '1';
    }
}
