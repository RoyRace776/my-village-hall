<?php

if (!defined('ABSPATH')) {
    exit;
}

class ClientAdminService {

    private const OPTION_NAME = 'myvh_client_admin_assignments';

    public function can_administer_blog(int $user_id = 0, int $blog_id = 0): bool {
        $user_id = $user_id > 0 ? $user_id : get_current_user_id();
        $blog_id = $blog_id > 0 ? $blog_id : get_current_blog_id();

        if ($user_id <= 0 || $blog_id <= 0) {
            return false;
        }

        if ($this->is_global_admin($user_id)) {
            return true;
        }

        if ($this->user_has_site_admin_cap($user_id, $blog_id)) {
            return true;
        }

        return in_array($user_id, $this->get_assigned_user_ids_for_blog($blog_id), true);
    }

    public function is_global_admin(int $user_id = 0): bool {
        $user_id = $user_id > 0 ? $user_id : get_current_user_id();

        if ($user_id <= 0) {
            return false;
        }

        if (is_multisite()) {
            return is_super_admin($user_id);
        }

        return user_can($user_id, 'manage_myvh');
    }

    public function get_assigned_user_ids_for_blog(int $blog_id): array {
        $assignments = $this->get_assignments();
        return $assignments[$blog_id] ?? [];
    }

    public function get_assigned_users_for_blog(int $blog_id): array {
        $user_ids = $this->get_assigned_user_ids_for_blog($blog_id);

        if (empty($user_ids)) {
            return [];
        }

        $users = get_users([
            'include' => $user_ids,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        return array_map(static function (WP_User $user): array {
            return [
                'ID' => (int) $user->ID,
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'user_login' => $user->user_login,
            ];
        }, $users);
    }

    public function add_assignment(int $blog_id, int $user_id): void {
        if ($blog_id <= 0 || $user_id <= 0) {
            return;
        }

        $assignments = $this->get_assignments();
        $existing = $assignments[$blog_id] ?? [];
        $existing[] = $user_id;
        $assignments[$blog_id] = array_values(array_unique(array_map('intval', $existing)));

        $this->save_assignments($assignments);
    }

    public function remove_assignment(int $blog_id, int $user_id): void {
        if ($blog_id <= 0 || $user_id <= 0) {
            return;
        }

        $assignments = $this->get_assignments();

        if (empty($assignments[$blog_id])) {
            return;
        }

        $assignments[$blog_id] = array_values(array_filter(
            array_map('intval', $assignments[$blog_id]),
            static fn($assigned_user_id): bool => (int) $assigned_user_id !== $user_id
        ));

        if (empty($assignments[$blog_id])) {
            unset($assignments[$blog_id]);
        }

        $this->save_assignments($assignments);
    }

    public function find_user(string $identifier): ?WP_User {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        if (is_numeric($identifier)) {
            $user = get_user_by('id', (int) $identifier);
            if ($user instanceof WP_User) {
                return $user;
            }
        }

        if (is_email($identifier)) {
            $user = get_user_by('email', $identifier);
            if ($user instanceof WP_User) {
                return $user;
            }
        }

        $user = get_user_by('login', $identifier);
        return $user instanceof WP_User ? $user : null;
    }

    public function get_accessible_sites_for_user(int $user_id = 0): array {
        $user_id = $user_id > 0 ? $user_id : get_current_user_id();

        if ($user_id <= 0) {
            return [];
        }

        $site_ids = [];

        if ($this->is_global_admin($user_id)) {
            if (is_multisite()) {
                foreach (get_sites(['number' => 0]) as $site) {
                    $site_ids[] = (int) $site->blog_id;
                }
            } else {
                $site_ids[] = get_current_blog_id();
            }
        } else {
            $site_ids = array_merge($site_ids, $this->get_assigned_blog_ids_for_user($user_id));

            if (is_multisite()) {
                foreach (get_blogs_of_user($user_id) as $blog) {
                    $blog_id = isset($blog->userblog_id) ? (int) $blog->userblog_id : 0;
                    if ($blog_id > 0 && $this->user_has_site_admin_cap($user_id, $blog_id)) {
                        $site_ids[] = $blog_id;
                    }
                }
            } elseif ($this->user_has_site_admin_cap($user_id, get_current_blog_id())) {
                $site_ids[] = get_current_blog_id();
            }
        }

        $site_ids = array_values(array_unique(array_filter(array_map('intval', $site_ids))));
        sort($site_ids);

        $sites = [];

        foreach ($site_ids as $site_id) {
            $sites[] = [
                'blog_id' => $site_id,
                'name' => $this->get_site_name($site_id),
                'url' => trailingslashit(get_home_url($site_id, '/portal/')),
                'is_current' => $site_id === get_current_blog_id(),
            ];
        }

        return $sites;
    }

    private function get_assignments(): array {
        $raw = is_multisite()
            ? get_site_option(self::OPTION_NAME, [])
            : get_option(self::OPTION_NAME, []);

        return $this->normalize_assignments($raw);
    }

    private function save_assignments(array $assignments): void {
        $normalized = $this->normalize_assignments($assignments);

        if (is_multisite()) {
            update_site_option(self::OPTION_NAME, $normalized);
            return;
        }

        update_option(self::OPTION_NAME, $normalized);
    }

    private function normalize_assignments($raw): array {
        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];

        foreach ($raw as $blog_id => $user_ids) {
            $resolved_blog_id = (int) $blog_id;
            if ($resolved_blog_id <= 0 || !is_array($user_ids)) {
                continue;
            }

            $normalized[$resolved_blog_id] = array_values(array_unique(array_filter(array_map('intval', $user_ids))));
        }

        ksort($normalized);

        return $normalized;
    }

    private function get_assigned_blog_ids_for_user(int $user_id): array {
        $blog_ids = [];

        foreach ($this->get_assignments() as $blog_id => $user_ids) {
            if (in_array($user_id, $user_ids, true)) {
                $blog_ids[] = (int) $blog_id;
            }
        }

        return $blog_ids;
    }

    private function user_has_site_admin_cap(int $user_id, int $blog_id): bool {
        if ($user_id <= 0 || $blog_id <= 0) {
            return false;
        }

        if (!is_multisite()) {
            return user_can($user_id, 'manage_myvh');
        }

        $switched = get_current_blog_id() !== $blog_id;

        if ($switched) {
            switch_to_blog($blog_id);
        }

        try {
            return user_can($user_id, 'manage_myvh');
        } finally {
            if ($switched) {
                restore_current_blog();
            }
        }
    }

    private function get_site_name(int $blog_id): string {
        if (!is_multisite()) {
            return (string) get_bloginfo('name');
        }

        $details = get_blog_details($blog_id);
        if (!$details) {
            return sprintf('Site %d', $blog_id);
        }

        return (string) $details->blogname;
    }
}