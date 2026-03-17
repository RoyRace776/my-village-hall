<?php

class MYVH_Asset_Loader {

    public static function init() {

        add_action('wp_enqueue_scripts', [self::class, 'enqueue_frontend']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin']);
    }

    /**
     * FRONTEND
     */
    public static function enqueue_frontend() {

        if (!is_singular()) return;

        global $post;
        $content = $post->post_content ?? '';

        if (has_shortcode($content, 'myvh_portal')) {
            self::enqueue_portal();
        }

        if (has_shortcode($content, 'myvh_public_calendar')) {
            self::enqueue_public_calendar();
        }
    }

    /**
     * ADMIN
     */
    public static function enqueue_admin($hook) {

        if (strpos($hook, 'myvh') === false) return;

        // global admin
        wp_enqueue_style('myvh-admin', MYVH_PLUGIN_URL . 'assets/css/admin.css');
        wp_enqueue_script('myvh-admin', MYVH_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], null, true);

        // calendar page only
        if (strpos($hook, 'myvh-calendar') !== false) {

            wp_enqueue_script('daypilot-lite', MYVH_PLUGIN_URL . 'assets/js/daypilot-all.min.js', [], null, true);

            wp_enqueue_style('myvh-calendar', MYVH_PLUGIN_URL . 'assets/css/calendar.css');

            wp_enqueue_script(
                'myvh-calendar',
                MYVH_PLUGIN_URL . 'assets/js/calendar.js',
                ['jquery', 'daypilot-lite'],
                null,
                true
            );

            wp_localize_script('myvh-calendar', 'myvhAdminCal', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('myvh-ajax-nonce'),
            ]);
        }
    }

    /**
     * ADMIN GLOBAL (forms, layout, etc.)
     */
    private static function enqueue_admin_global() {

        wp_enqueue_style(
            'myvh-admin-global',
            MYVH_PLUGIN_URL . 'assets/css/admin-global.css',
            [],
            '1.0'
        );

        wp_enqueue_script(
            'myvh-admin-global',
            MYVH_PLUGIN_URL . 'assets/js/admin-global.js',
            ['jquery'],
            '1.0',
            true
        );
    }

    /**
     * ADMIN CALENDAR (heavy stuff)
     */
    private static function enqueue_admin_calendar() {

        wp_enqueue_style(
            'myvh-admin-calendar',
            MYVH_PLUGIN_URL . 'assets/css/admin-calendar.css',
            [],
            '1.0'
        );

        wp_enqueue_script(
            'myvh-admin-calendar',
            MYVH_PLUGIN_URL . 'assets/js/admin-calendar.js',
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('myvh-admin-calendar', 'myvhAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('myvh_admin'),
        ]);
    }

    /**
     * PORTAL
     */
    public static function enqueue_portal() {

        // Base portal styling
        wp_enqueue_style(
            'myvh-portal',
            MYVH_PLUGIN_URL . 'assets/css/portal.css',
            [],
            '1.0'
        );

        // Fonts (filterable)
        $fonts_url = apply_filters(
            'myvh_portal_fonts_url',
            'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=DM+Sans:wght@300;400;500&display=swap'
        );

        if ($fonts_url) {
            wp_enqueue_style('myvh-google-fonts', $fonts_url, [], null);
        }

        // Dashboard styles
        wp_enqueue_style(
            'myvh-dashboard',
            MYVH_PLUGIN_URL . 'assets/css/dashboard.css',
            $fonts_url ? ['myvh-google-fonts'] : [],
            '1.0'
        );

        // Portal app (React-style controller)
        wp_enqueue_script(
            'myvh-portal-app',
            MYVH_PLUGIN_URL . 'assets/js/portal-app.js',
            [],
            '1.0',
            true
        );

        wp_localize_script(
            'myvh-portal-app',
            'myvhPortal',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('myvh_portal'),
            ]
        );
    }


    /**
     * PUBLIC CALENDAR
     */
    public static function enqueue_public_calendar() {

        wp_enqueue_style(
            'myvh-calendar',
            MYVH_PLUGIN_URL . 'assets/css/calendar.css',
            [],
            '1.0'
        );

        wp_enqueue_script(
            'myvh-calendar',
            MYVH_PLUGIN_URL . 'assets/js/public-calendar.js',
            [],
            '1.0',
            true
        );

        wp_localize_script('myvh-calendar', 'myvhCalendar', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }


}
