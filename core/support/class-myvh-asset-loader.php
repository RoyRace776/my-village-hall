<?php

class MYVH_Asset_Loader {

    public static function init() {
        add_action( 'wp_enqueue_scripts',    [ self::class, 'enqueue_frontend' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin'    ] );
    }

    // ── Frontend ──────────────────────────────────────────────────────────────

    public static function enqueue_frontend() {

        if ( ! is_singular() ) return;

        $content = get_queried_object()->post_content ?? '';

        if ( has_shortcode( $content, 'myvh_portal' ) ) {
            self::enqueue_portal();
        }

        if ( has_shortcode( $content, 'myvh_public_calendar' ) ) {
            self::enqueue_public_calendar();
        }
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    public static function enqueue_admin( $hook ) {

        if ( strpos( $hook, 'myvh' ) === false && strpos( $hook, 'my-village-hall' ) === false ) return;

        // Shared across all plugin admin pages
        wp_enqueue_style(  'myvh-admin', MYVH_PLUGIN_URL . 'assets/css/admin.css', [], MYVH_VERSION );
        wp_enqueue_script( 'myvh-admin', MYVH_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], MYVH_VERSION, true );

        // Bookings list page
        if ( strpos( $hook, 'my-village-hall' ) !== false ) {
            wp_enqueue_script(
                'myvh-bookings',
                MYVH_PLUGIN_URL . 'assets/js/bookings.js',
                [ 'jquery', 'myvh-admin' ],
                MYVH_VERSION,
                true
            );
        }

        // Calendar page only
        if ( strpos( $hook, 'myvh-calendar' ) !== false ) {

            wp_enqueue_script(
                'daypilot',
                MYVH_PLUGIN_URL . 'assets/js/daypilot-all.min.js',
                [],
                MYVH_VERSION,
                true
            );

            wp_enqueue_script(
                'myvh-calendar-core',
                MYVH_PLUGIN_URL . 'assets/js/calendar-core.js',
                ['daypilot'],
                '1.0',
                true
            );

            wp_enqueue_script(
                'myvh-booking-modal',
                MYVH_PLUGIN_URL . 'assets/js/booking-modal.js',
                ['myvh-calendar-core'], // optional but safe
                '1.0',
                true
            );

            wp_enqueue_script(
                'myvh-calendar-admin',
                MYVH_PLUGIN_URL . 'assets/js/calendar-admin.js',
                ['jquery', 'myvh-booking-modal'], // ✅ critical
                '1.0',
                true
            );

            wp_localize_script( 'myvh-calendar-admin', 'myvhCal', [
                'ajax_url'         => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'myvh_calendar' ),
                'headerDateFormat' => myvh_setting( 'general.calendar_date_format', 'd MMM' ),
            ] );
        }
    }

    // ── Portal (frontend, [myvh_portal] shortcode) ────────────────────────────

    public static function enqueue_portal() {

        // Google Fonts (filterable so the theme can suppress the duplicate)
        $fonts_url = apply_filters(
            'myvh_portal_fonts_url',
            'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=DM+Sans:wght@300;400;500&display=swap'
        );

        if ( $fonts_url ) {
            wp_enqueue_style( 'myvh-google-fonts', $fonts_url, [], null );
        }

        wp_enqueue_style(
            'myvh-dashboard',
            MYVH_PLUGIN_URL . 'assets/css/dashboard.css',
            $fonts_url ? [ 'myvh-google-fonts' ] : [],
            MYVH_VERSION
        );

        wp_enqueue_style(
            'myvh-login',
            MYVH_PLUGIN_URL . 'assets/css/login.css',
            [ 'myvh-dashboard' ],
            MYVH_VERSION
        );

        wp_enqueue_script(
            'daypilot',
            MYVH_PLUGIN_URL . 'assets/js/daypilot-all.min.js',
            [],
            MYVH_VERSION,
            true
        );

        wp_enqueue_script(
            'myvh-calendar-core',
            MYVH_PLUGIN_URL . 'assets/js/calendar-core.js',
            [ 'daypilot' ],
            MYVH_VERSION,
            true
        );

        wp_enqueue_script(
            'myvh-booking-modal',
            MYVH_PLUGIN_URL . 'assets/js/booking-modal.js',
            ['myvh-calendar-core'], // optional but safe
            '1.0',
            true
        );

        wp_enqueue_script(
            'myvh-calendar-portal',
            MYVH_PLUGIN_URL . 'assets/js/calendar-portal.js',
            [ 'myvh-booking-modal' ],
            MYVH_VERSION,
            true
        );

        wp_enqueue_script(
            'myvh-bookings',
            MYVH_PLUGIN_URL . 'assets/js/bookings.js',
            [ 'jquery' ],
            MYVH_VERSION,
            true
        );

        wp_enqueue_script(
            'myvh-dashboard',
            MYVH_PLUGIN_URL . 'assets/js/dashboard.js',
            [ 'jquery', 'myvh-calendar-portal', 'myvh-bookings' ],
            MYVH_VERSION,
            true
        );

        wp_enqueue_script(
            'myvh-portal-app',
            MYVH_PLUGIN_URL . 'assets/js/portal-app.js',
            [ 'myvh-dashboard' ],
            MYVH_VERSION,
            true
        );

        wp_localize_script( 'myvh-portal-app', 'myvhPortal', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'myvh_portal' ),
        ] );

        // myvhCal used by calendar-core.js via calendar-portal.js
        wp_localize_script( 'myvh-calendar-portal', 'myvhCal', [
            'ajax_url'         => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'myvh_calendar' ),
            'headerDateFormat' => myvh_setting( 'general.calendar_date_format', 'd MMM' ),
        ] );
    }

    // ── Public calendar ([myvh_public_calendar] shortcode) ────────────────────

    public static function enqueue_public_calendar() {

        wp_enqueue_script(
            'daypilot',
            MYVH_PLUGIN_URL . 'assets/js/daypilot-all.min.js',
            [],
            MYVH_VERSION,
            true
        );

        wp_enqueue_script(
            'myvh-calendar-core',
            MYVH_PLUGIN_URL . 'assets/js/calendar-core.js',
            [ 'daypilot' ],
            MYVH_VERSION,
            true
        );

        wp_enqueue_script(
            'myvh-calendar-public',
            MYVH_PLUGIN_URL . 'assets/js/calendar-public.js',
            [ 'myvh-calendar-core' ],
            MYVH_VERSION,
            true
        );

        wp_localize_script( 'myvh-calendar-public', 'myvhCalConfig', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'myvh_calendar' ),
            'view'    => 'month',
        ] );
    }
}
