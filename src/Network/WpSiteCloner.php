<?php

namespace MYVH\Network;

use WP_Error;

class WpSiteCloner {

    public function clone(int $source_id, array $target, array $context = []): int|WP_Error {

        // 1. Create site
        $network = get_network();
        $subdomain = sanitize_title($target['name']);
        $provision_id = $context['provision_id'];

        if (is_subdomain_install()) {
            // ✅ Subdomain mode
            $domain = $subdomain . '.' . $network->domain;
            $path   = '/';
        } else {
            // ✅ Subdirectory mode
            $domain = $network->domain;
            $path   = '/' . $subdomain . '/';
        }
        $blog_id = wpmu_create_blog(
            $domain,
            $path,
            $target['title'],
            $context['user_id'] ?? get_current_user_id(),
            [],
            $network->id
        );

        if (is_wp_error($blog_id)) {
            return $blog_id;
        }

        // 2. Copy data
        $this->copy_options($source_id, $blog_id);
        $this->copy_posts($source_id, $blog_id);

        // 3. Assign admin
        if (!empty($context['user_id'])) {
            add_user_to_blog($blog_id, $context['user_id'], 'administrator');
        }

        // 4. Custom hooks (your system)
        do_action('myvh_site_cloned', $blog_id, $context);

        return (int) $blog_id;
    }

    private function copy_options(int $source_id, int $target_id): void {

        switch_to_blog($source_id);
        $options = wp_load_alloptions();
        restore_current_blog();

        switch_to_blog($target_id);

        foreach ($options as $key => $value) {
            // Skip dangerous/system options
            if (in_array($key, [
                'siteurl',
                'home',
                'blogname',
                'admin_email',
            ], true)) {
                continue;
            }

            update_option($key, maybe_unserialize($value));
        }

        restore_current_blog();
    }

    private function copy_posts(int $source_id, int $target_id): void {

        switch_to_blog($source_id);

        $posts = get_posts([
            'post_type' => ['page', 'post'],
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
            ]);

            if ($new_id && !is_wp_error($new_id)) {
                $this->copy_post_meta($post->ID, $new_id, $source_id);
            }
        }

        restore_current_blog();
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
}