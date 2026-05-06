<?php

namespace MYVH\Network;

use WP_Error;

class WpSiteCloner {

    public function clone(int $source_id, array $target, array $context = []): int|WP_Error {

        $network = get_network();

        $subdomain = sanitize_title($target['name']);
        $subdomain = trim($subdomain, '-');
        $user_id   = $context['user_id'] ?? get_current_user_id();

        // -------------------------------------------------
        // 1. Decide mode explicitly (safer than relying only on is_subdomain_install)
        // -------------------------------------------------
        $mode = $context['force_mode'] ?? (is_subdomain_install() ? 'subdomain' : 'subdirectory');

        $is_subdomain_mode = ($mode === 'subdomain');

        if ($is_subdomain_mode) {

            // 🌐 Subdomain mode: client.site.com
            $domain = $subdomain . '.' . $network->domain;
            $path   = '/';

        } else {

            // 📁 Subdirectory mode: site.com/client
            $domain = $network->domain;
            $path   = '/' . $subdomain . '/';
        }

        // -------------------------------------------------
        // 2. Suppress default emails (good for provisioning systems)
        // -------------------------------------------------
        $suppress_newsite_email = fn() => false;
        $suppress_welcome_email = fn() => false;

        add_filter('send_newsite_email', $suppress_newsite_email);
        add_filter('wpmu_welcome_notification', $suppress_welcome_email);

        // -------------------------------------------------
        // 3. Check if site exists (important to do this before creating the site to avoid orphaned sites in case of errors)
        // -------------------------------------------------
        $existing = get_site_by_path($domain, $path);

        if ($existing) {
            return new WP_Error('site_exists', 'Site ' . $existing->id . ' already exists: ' . $existing->domain . $existing->path);
        }

        try {

            $blog_id = wpmu_create_blog(
                $domain,
                $path,
                $target['title'],
                $user_id,
                [],
                $network->id
            );

        } finally {
            remove_filter('send_newsite_email', $suppress_newsite_email);
            remove_filter('wpmu_welcome_notification', $suppress_welcome_email);
        }

        if (is_wp_error($blog_id)) {
            return $blog_id;
        }

        // -------------------------------------------------
        // 3. Clone data
        // -------------------------------------------------
        $this->copy_options($source_id, $blog_id);
        $post_map = $this->copy_posts($source_id, $blog_id);
        $this->fix_front_page($source_id, $blog_id, $post_map);

        // -------------------------------------------------
        // 4. Assign admin
        // -------------------------------------------------
        if ($user_id) {
            add_user_to_blog($blog_id, $user_id, 'administrator');
        }

        // -------------------------------------------------
        // 5. Hook for your provisioning system
        // -------------------------------------------------
        do_action('myvh_site_cloned', $blog_id, $context);
        return (int) $blog_id;
    }

    private function copy_options(int $source_id, int $target_id): void {

        switch_to_blog($source_id);
        $options = wp_load_alloptions();
        restore_current_blog();

        switch_to_blog($target_id);

        foreach ($options as $key => $value) {
            if (str_starts_with($key, '_transient')) {
                continue;
            }

            if (in_array($key, ['siteurl', 'home', 'blogname', 'admin_email'], true)) {
                continue;
            }

            update_option($key, maybe_unserialize($value));
        }

        restore_current_blog();
    }

    private function copy_posts(int $source_id, int $target_id): array {

        $map = [];

        switch_to_blog($source_id);

        $posts = get_posts([
            'post_type'   => ['page', 'post'],
            'numberposts' => -1,
        ]);

        restore_current_blog();

        switch_to_blog($target_id);

        foreach ($posts as $post) {

            $new_id = wp_insert_post([
                'post_title'   => $post->post_title,
                'post_content' => $post->post_content,
                'post_status'  => 'publish',
                'post_type'    => $post->post_type,
                'post_name'    => $post->post_name,
                'post_excerpt' => $post->post_excerpt,
                'post_parent'  => 0, // We'll fix this later if needed
                'menu_order'   => $post->menu_order,
            ]);

            if ($new_id && !is_wp_error($new_id)) {
                $map[$post->ID] = $new_id;
                $this->copy_post_meta($post->ID, $new_id, $source_id);
            }
        }

        restore_current_blog();

        return $map;
    }

    private function copy_post_meta(int $source_post_id, int $target_post_id, int $source_blog_id): void {

        switch_to_blog($source_blog_id);
        $meta = get_post_meta($source_post_id);
        restore_current_blog();

        foreach ($meta as $key => $values) {
            foreach ($values as $value) {
                add_post_meta($target_post_id, $key, maybe_unserialize($value));
            }
        }
    }

    private function fix_front_page(int $source_id, int $target_id, array $map): void {

        switch_to_blog($source_id);

        $front_page_id = get_option('page_on_front');
        $show_on_front = get_option('show_on_front');

        restore_current_blog();

        if ($show_on_front !== 'page' || empty($map[$front_page_id])) {
            return;
        }

        switch_to_blog($target_id);

        update_option('show_on_front', 'page');
        update_option('page_on_front', $map[$front_page_id]);

        restore_current_blog();
    }
}