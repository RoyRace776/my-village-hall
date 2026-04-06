<?php
namespace MYVH\Core\Support;

use MYVH\Calendar\CalendarStatusColours;

class AssetLoader {

    /**
     * Get a version string for an asset based on file modification time (for cache busting).
     * Falls back to plugin version if file not found.
     */
    private static function asset_version( $relative_path ): string {
        $full_path = MYVH_PLUGIN_DIR . ltrim( $relative_path, '/\\' );

        if ( file_exists( $full_path ) ) {
            $mtime = filemtime( $full_path );
            if ( $mtime ) {
                return (string) $mtime;
            }
        }

        return MYVH_VERSION;
    }

    /**
     * Register hooks to enqueue frontend and admin assets.
     */
    public static function init(): void {
        add_action( 'wp_enqueue_scripts',    [ self::class, 'enqueue_frontend' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin'    ] );
    }

    // ── Frontend ──────────────────────────────────────────────────────────────

    /**
     * Enqueue frontend assets.
     *
     * Portal assets are enqueued by PortalShortcode::render() so that
     * PortalBootstrapDataService data is already available for wp_localize_script.
     * Public calendar assets are enqueued by CalendarShortcode::render() to
     * support per-instance container IDs and REST config.
     */
    public static function enqueue_frontend(): void {
        self::register_public_calendar_assets();
    }

    /**
     * Register scripts and styles for the public calendar (not enqueued by default).
     */
    public static function register_public_calendar_assets(): void {

        // Use the plugin-bundled DayPilot build so public calendar works in local/offline environments.
        wp_register_script(
            'daypilot',
            MYVH_PLUGIN_URL . 'assets/js/daypilot-all.min.js',
            [],
            MYVH_VERSION,
            true
        );

        wp_register_script(
            'myvh-public-calendar',
            MYVH_PLUGIN_URL . 'assets/js/public-calendar.js',
            [ 'daypilot' ],
            self::asset_version( 'assets/js/public-calendar.js' ),
            true
        );

        wp_register_style(
            'myvh-calendar-theme',
            MYVH_PLUGIN_URL . 'assets/css/calendar.css',
            [],
            self::asset_version( 'assets/css/calendar.css' )
        );

        wp_register_style(
            'myvh-public-calendar',
            MYVH_PLUGIN_URL . 'assets/css/public-calendar.css',
            [ 'myvh-calendar-theme' ],
            MYVH_VERSION
        );
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    /**
     * Enqueue admin assets for plugin admin pages.
     * @param string $hook The current admin page hook
     */
    public static function enqueue_admin( $hook ): void {

        if ( strpos( $hook, 'myvh' ) === false && strpos( $hook, 'my-village-hall' ) === false ) return;

        // Shared across all plugin admin pages
        wp_enqueue_style(
            'myvh-admin',
            MYVH_PLUGIN_URL . 'assets/css/admin.css',
            [],
            self::asset_version( 'assets/css/admin.css' )
        );

        wp_enqueue_script(
            'myvh-admin',
            MYVH_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            self::asset_version( 'assets/js/admin.js' ),
            true
        );

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

            wp_enqueue_style(
                'myvh-calendar-theme',
                MYVH_PLUGIN_URL . 'assets/css/calendar.css',
                [],
                self::asset_version( 'assets/css/calendar.css' )
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
                ['daypilot'],
                self::asset_version( 'assets/js/calendar-core.js' ),
                true
            );

            wp_enqueue_script(
                'myvh-booking-modal-create',
                MYVH_PLUGIN_URL . 'assets/js/booking-modal-create.js',
                ['myvh-calendar-core'], // optional but safe
                self::asset_version( 'assets/js/booking-modal-create.js' ),
                true
            );

            wp_enqueue_script(
                'myvh-booking-modal-view',
                MYVH_PLUGIN_URL . 'assets/js/booking-modal-view.js',
                ['myvh-calendar-core'], // optional but safe
                self::asset_version( 'assets/js/booking-modal-view.js' ),
                true
            );

            wp_enqueue_script(
                'myvh-calendar-admin',
                MYVH_PLUGIN_URL . 'assets/js/calendar-admin.js',
                ['jquery', 'myvh-booking-modal-create', 'myvh-booking-modal-view'], // critical dependency
                self::asset_version( 'assets/js/calendar-admin.js' ),
                true
            );

            // wp_localize_script for myvhCal is called from MyVillageHall::render_calendar_page()
            // after AvailabilityService resolves visible hours.
        }
    }

    // ── Portal (frontend, [myvh_portal] shortcode) ────────────────────────────

    /**
     * Enqueue styles and scripts for the [myvh_portal] shortcode.
     *
     * This method only handles asset registration/enqueueing.
     * wp_localize_script calls are made by PortalShortcode::render() after
     * PortalBootstrapDataService has resolved all request-specific values,
     * ensuring database reads happen once and at the appropriate time.
     */
    public static function enqueue_portal_assets(): void {

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
            self::asset_version( 'assets/css/dashboard.css' )
        );

        wp_enqueue_style( 'myvh-calendar-theme' );

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
            self::asset_version( 'assets/js/calendar-core.js' ),
            true
        );

        wp_enqueue_script(
            'myvh-booking-modal-create',
            MYVH_PLUGIN_URL . 'assets/js/booking-modal-create.js',
            ['myvh-calendar-core'], // optional but safe
            self::asset_version( 'assets/js/booking-modal-create.js' ),
            true
        );

        wp_enqueue_script(
            'myvh-booking-modal-view',
            MYVH_PLUGIN_URL . 'assets/js/booking-modal-view.js',
            ['myvh-calendar-core'], // optional but safe
            self::asset_version( 'assets/js/booking-modal-view.js' ),
            true
        );

        wp_enqueue_script(
            'myvh-calendar-portal',
            MYVH_PLUGIN_URL . 'assets/js/calendar-portal.js',
            [ 'myvh-booking-modal-create', 'myvh-booking-modal-view' ], // critical dependencies
            self::asset_version( 'assets/js/calendar-portal.js' ),
            true
        );

        wp_enqueue_script(
            'myvh-bookings',
            MYVH_PLUGIN_URL . 'assets/js/bookings.js',
            [ 'jquery' ],
            self::asset_version( 'assets/js/bookings.js' ),
            true
        );

        wp_enqueue_script(
            'myvh-dashboard',
            MYVH_PLUGIN_URL . 'assets/js/dashboard.js',
            [ 'jquery', 'myvh-calendar-portal', 'myvh-bookings' ],
            self::asset_version( 'assets/js/dashboard.js' ),
            true
        );

        wp_enqueue_script(
            'myvh-portal-app',
            MYVH_PLUGIN_URL . 'assets/js/portal-app.js',
            [ 'myvh-dashboard' ],
            self::asset_version( 'assets/js/portal-app.js' ),
            true
        );
    }

    // ── Public calendar ([myvh_public_calendar] shortcode) ────────────────────

    /**
     * Enqueue assets for the public calendar shortcode.
     */
    public static function enqueue_public_calendar(): void {

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

        // Pass config to JS for public calendar
        wp_localize_script( 'myvh-calendar-public', 'myvhCalConfig', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'myvh_calendar' ),
            'view'    => 'month',
            'statusColors' => CalendarStatusColours::map(),
        ] );
    }
}