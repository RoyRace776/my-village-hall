<?php

declare(strict_types=1);

namespace MYVH\Pages\Infrastructure;

use RuntimeException;
use WP_Error;
use WP_Post;

class WordPressPageRepository {
    private const MANAGED_META_KEY = '_myvh_managed';

    public function findBySlug(string $slug): ?WP_Post {
        $page = get_page_by_path($slug, 'OBJECT', 'page');

        return $page instanceof WP_Post ? $page : null;
    }

    public function create(array $data): int {
        $result = wp_insert_post($data, true);

        if ($result instanceof WP_Error) {
            throw new RuntimeException($result->get_error_message());
        }

        return (int) $result;
    }

    public function update(int $id, array $data): void {
        $data['ID'] = $id;
        $result = wp_update_post($data, true);

        if ($result instanceof WP_Error) {
            throw new RuntimeException($result->get_error_message());
        }
    }

    public function markManaged(int $id): void {
        update_post_meta($id, self::MANAGED_META_KEY, '1');
    }

    public function isManaged(int $id): bool {
        return get_post_meta($id, self::MANAGED_META_KEY, true) === '1';
    }
}
